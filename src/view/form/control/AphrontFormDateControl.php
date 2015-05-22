<?php

final class AphrontFormDateControl extends AphrontFormControl {

  private $initialTime;
  private $zone;

  private $valueDay;
  private $valueMonth;
  private $valueYear;
  private $valueTime;
  private $allowNull;
  private $continueOnInvalidDate = false;
  private $isTimeDisabled;
  private $isDisabled;
  private $endDateID;

  public function setAllowNull($allow_null) {
    $this->allowNull = $allow_null;
    return $this;
  }

  public function setIsTimeDisabled($is_disabled) {
    $this->isTimeDisabled = $is_disabled;
    return $this;
  }

  public function setEndDateID($value) {
    $this->endDateID = $value;
    return $this;
  }

  const TIME_START_OF_DAY         = 'start-of-day';
  const TIME_END_OF_DAY           = 'end-of-day';
  const TIME_START_OF_BUSINESS    = 'start-of-business';
  const TIME_END_OF_BUSINESS      = 'end-of-business';

  public function setInitialTime($time) {
    $this->initialTime = $time;
    return $this;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $day = $request->getInt($this->getDayInputName());
    $month = $request->getInt($this->getMonthInputName());
    $year = $request->getInt($this->getYearInputName());
    $time = $request->getStr($this->getTimeInputName());
    $enabled = $request->getBool($this->getCheckboxInputName());

    if ($this->allowNull && !$enabled) {
      $this->setError(null);
      $this->setValue(null);
      return;
    }

    $err = $this->getError();

    if ($day || $month || $year || $time) {
      $this->valueDay = $day;
      $this->valueMonth = $month;
      $this->valueYear = $year;
      $this->valueTime = $time;

      // Assume invalid.
      $err = 'Invalid';

      $zone = $this->getTimezone();

      try {
        $date = new DateTime("{$year}-{$month}-{$day} {$time}", $zone);
        $value = $date->format('U');
      } catch (Exception $ex) {
        $value = null;
      }

      if ($value) {
        $this->setValue($value);
        $err = null;
      } else {
        $this->setValue(null);
      }
    } else {
      $value = $this->getInitialValue();
      if ($value) {
        $this->setValue($value);
      } else {
        $this->setValue(null);
      }
    }

    $this->setError($err);

    return $this->getValue();
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-date';
  }

  public function setValue($epoch) {
    if ($epoch instanceof AphrontFormDateControlValue) {
      $this->continueOnInvalidDate = true;
      $this->valueYear  = $epoch->getValueYear();
      $this->valueMonth = $epoch->getValueMonth();
      $this->valueDay   = $epoch->getValueDay();
      $this->valueTime  = $epoch->getValueTime();
      $this->allowNull = $epoch->getOptional();
      $this->isDisabled = $epoch->isDisabled();

      return parent::setValue($epoch->getEpoch());
    }

    $result = parent::setValue($epoch);

    if ($epoch === null) {
      return $result;
    }

    $readable = $this->formatTime($epoch, 'Y!m!d!g:i A');
    $readable = explode('!', $readable, 4);

    $this->valueYear  = $readable[0];
    $this->valueMonth = $readable[1];
    $this->valueDay   = $readable[2];
    $this->valueTime  = $readable[3];

    return $result;
  }

  private function getMinYear() {
    $cur_year = $this->formatTime(
      time(),
      'Y');
    $val_year = $this->getYearInputValue();

    return min($cur_year, $val_year) - 3;
  }

  private function getMaxYear() {
    $cur_year = $this->formatTime(
      time(),
      'Y');
    $val_year = $this->getYearInputValue();

    return max($cur_year, $val_year) + 3;
  }

  private function getDayInputValue() {
    return $this->valueDay;
  }

  private function getMonthInputValue() {
    return $this->valueMonth;
  }

  private function getYearInputValue() {
    return $this->valueYear;
  }

  private function getTimeInputValue() {
    return $this->valueTime;
  }

  private function formatTime($epoch, $fmt) {
    return phabricator_format_local_time(
      $epoch,
      $this->user,
      $fmt);
  }

  private function getDayInputName() {
    return $this->getName().'_d';
  }

  private function getMonthInputName() {
    return $this->getName().'_m';
  }

  private function getYearInputName() {
    return $this->getName().'_y';
  }

  private function getTimeInputName() {
    return $this->getName().'_t';
  }

  private function getCheckboxInputName() {
    return $this->getName().'_e';
  }

  protected function renderInput() {

    $disabled = null;
    if ($this->getValue() === null && !$this->continueOnInvalidDate) {
      $this->setValue($this->getInitialValue());
      if ($this->allowNull) {
        $disabled = 'disabled';
      }
    }

    if ($this->isDisabled) {
      $disabled = 'disabled';
    }

    $min_year = $this->getMinYear();
    $max_year = $this->getMaxYear();

    $days = range(1, 31);
    $days = array_fuse($days);

    $months = array(
      1 => pht('Jan'),
      2 => pht('Feb'),
      3 => pht('Mar'),
      4 => pht('Apr'),
      5 => pht('May'),
      6 => pht('Jun'),
      7 => pht('Jul'),
      8 => pht('Aug'),
      9 => pht('Sep'),
      10 => pht('Oct'),
      11 => pht('Nov'),
      12 => pht('Dec'),
    );

    $checkbox = null;
    if ($this->allowNull) {
      $checkbox = javelin_tag(
        'input',
        array(
          'type' => 'checkbox',
          'name' => $this->getCheckboxInputName(),
          'sigil' => 'calendar-enable',
          'class' => 'aphront-form-date-enabled-input',
          'value' => 1,
          'checked' => ($disabled === null ? 'checked' : null),
        ));
    }

    $years = range($this->getMinYear(), $this->getMaxYear());
    $years = array_fuse($years);

    $days_sel = AphrontFormSelectControl::renderSelectTag(
      $this->getDayInputValue(),
      $days,
      array(
        'name' => $this->getDayInputName(),
        'sigil' => 'day-input',
      ));

    $months_sel = AphrontFormSelectControl::renderSelectTag(
      $this->getMonthInputValue(),
      $months,
      array(
        'name' => $this->getMonthInputName(),
        'sigil' => 'month-input',
      ));

    $years_sel = AphrontFormSelectControl::renderSelectTag(
      $this->getYearInputValue(),
      $years,
      array(
        'name'  => $this->getYearInputName(),
        'sigil' => 'year-input',
      ));

    $cicon = id(new PHUIIconView())
      ->setIconFont('fa-calendar');

    $cal_icon = javelin_tag(
      'a',
      array(
        'href'  => '#',
        'class' => 'calendar-button',
        'sigil' => 'calendar-button',
      ),
      $cicon);

    $values = $this->getTimeTypeaheadValues();

    $time_id = celerity_generate_unique_node_id();
    Javelin::initBehavior('time-typeahead', array(
      'startTimeID' => $time_id,
      'endTimeID' => $this->endDateID,
      'timeValues' => $values,
      ));


    $time_sel = javelin_tag(
      'input',
      array(
        'autocomplete' => 'off',
        'name'  => $this->getTimeInputName(),
        'sigil' => 'time-input',
        'value' => $this->getTimeInputValue(),
        'type'  => 'text',
        'class' => 'aphront-form-date-time-input',
      ),
      '');

    $time_div = javelin_tag(
      'div',
      array(
        'id' => $time_id,
        'class' => 'aphront-form-date-time-input-container',
      ),
      $time_sel);

    Javelin::initBehavior('fancy-datepicker', array());

    $classes = array();
    $classes[] = 'aphront-form-date-container';
    if ($disabled) {
      $classes[] = 'datepicker-disabled';
    }
    if ($this->isTimeDisabled) {
      $classes[] = 'no-time';
    }

    return javelin_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
        'sigil' => 'phabricator-date-control',
        'meta'  => array(
          'disabled' => (bool)$disabled,
        ),
        'id' => $this->getID(),
      ),
      array(
        $checkbox,
        $days_sel,
        $months_sel,
        $years_sel,
        $cal_icon,
        $time_div,
      ));
  }

  private function getTimezone() {
    if ($this->zone) {
      return $this->zone;
    }

    $user = $this->getUser();
    if (!$this->getUser()) {
      throw new PhutilInvalidStateException('setUser');
    }

    $user_zone = $user->getTimezoneIdentifier();
    $this->zone = new DateTimeZone($user_zone);
    return $this->zone;
  }

  private function getInitialValue() {
    $zone = $this->getTimezone();

    // TODO: We could eventually allow these to be customized per install or
    // per user or both, but let's wait and see.
    switch ($this->initialTime) {
      case self::TIME_START_OF_DAY:
      default:
        $time = '12:00 AM';
        break;
      case self::TIME_START_OF_BUSINESS:
        $time = '9:00 AM';
        break;
      case self::TIME_END_OF_BUSINESS:
        $time = '5:00 PM';
        break;
      case self::TIME_END_OF_DAY:
        $time = '11:59 PM';
        break;
    }

    $today = $this->formatTime(time(), 'Y-m-d');
    try {
      $date = new DateTime("{$today} {$time}", $zone);
      $value = $date->format('U');
    } catch (Exception $ex) {
      $value = null;
    }

    return $value;
  }

  private function getTimeTypeaheadValues() {
    $times = array();
    $am_pm_list = array('AM', 'PM');

    foreach ($am_pm_list as $am_pm) {
      for ($hour = 0; $hour < 12; $hour++) {
        $actual_hour = ($hour == 0) ? 12 : $hour;
        $times[] = $actual_hour.':00 '.$am_pm;
        $times[] = $actual_hour.':30 '.$am_pm;
      }
    }

    foreach ($times as $key => $time) {
      $times[$key] = array($key, $time);
    }
    return $times;
  }

}
