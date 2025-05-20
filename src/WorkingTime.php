<?php
/**
 * @copyright 2016, Aleksandr Bushlanov
 * @package gtvolk\WorkingTime
 * @link https://github.com/GT-Volk/
 * @license https://github.com/GT-Volk/working-time/blob/master/LICENSE.md
 */

namespace gtvolk\WorkingTime;

use DateTime;

/**
 * Класс рассчитывает временнЫе интервалы, учитывая рабочие часы и выходные дни.
 *
 * @property DateTime $dateTime
 * @property array $workingDays
 * @property array $weekends
 * @property array $holidays
 *
 * @since  1.0.3
 *
 * @author Aleksandr Bushlanov <alex@bushlanov.pro>
 */
class WorkingTime
{
    /**
     * @var DateTime
     */
    public $dateTime;

    /**
     * @var array
     */
    public $workingDays;

    /**
     * @var null|array
     */
    public $weekends;

    /**
     * @var null|array
     */
    public $holidays;

    /**
     * WorkingTime constructor.
     *
     * @param array $workTimeConfig
     * @param string $dateTime
     * @throws \Exception
     */
    public function __construct(array $workTimeConfig, $dateTime = 'now')
    {
        $this->dateTime = new DateTime($dateTime);
        foreach ($workTimeConfig as $name => $value) {
            $this->$name = $value;
        }
    }

    /**
     * Фурмирует строку дня.
     *
     * @param null|string $date
     * @param string $format
     * @return string
     */
    private function buildDataPartString($date, $format)
    {
        return null === $date ? $this->dateTime->format($format) : date($format, strtotime($date));
    }

    /**
     * Формирует дату из строки.
     *
     * @param string $date
     * @return DateTime
     * @throws \Exception
     */
    private function buildDate($date = null)
    {
        if (null === $date) {
            $date = $this->dateTime->format('Y-m-d H:i');
        }

        return new DateTime($date);
    }

    /**
     * Проверяет является ли дата праздничным днём.
     *
     * @param string $date
     * @return bool
     */
    public function isHoliday($date = null)
    {
        if (empty($this->holidays)) {
            return false; // Если не указаны праздничные дни, то день рабочий.
        }
        $day = $this->buildDataPartString($date, 'd-m');

        return \in_array($day, $this->holidays, false);
    }

    /**
     * Проверяет является ли дата выходным днём.
     *
     * @param string $date
     * @return bool
     */
    public function isWeekend($date = null)
    {
        if (empty($this->weekends)) {
            return false; // Если не указаны выходные дни, то день рабочий.
        }
        $day = $this->buildDataPartString($date, 'w');

        return \in_array($day, $this->weekends, false);
    }

    /**
     * Проверяет евляется ли дата рабочим днём.
     *
     * Формат даты - "Y-m-d"
     * @param string $date
     * @return bool
     */
    public function isWorkingDate($date = null)
    {
        return !($this->isWeekend($date) || $this->isHoliday($date));
    }

    /**
     * Проверяет евляется ли время рабочим.
     *
     * @param string|null $time Формат времени - "H:i" или полный "Y-m-d H:i"
     * @return bool
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function isWorkingTime($time = null)
    {
        if (null === $time) {
            $dateTime = $this->dateTime;
        } elseif (self::validateDate($time, 'H:i')) {
            $dateTime = new DateTime($this->dateTime->format('Y-m-d') . ' ' . $time);
        } elseif (self::validateDate($time, 'Y-m-d H:i')) {
            $dateTime = new DateTime($time);
        } else {
            throw new \InvalidArgumentException("Date `{$time}` isn't a valid date. Dates should be formatted as Y-m-d or H:i, e.g. `2016-10-27` or `17:30`.");
        }

        list($jobStart, $jobEnd) = explode('-', $this->workingDays[$dateTime->format('w')]);

        $dtStart = new DateTime($dateTime->format('Y-m-d') . ' ' . $jobStart);
        $dtEnd = new DateTime($dateTime->format('Y-m-d') . ' ' . $jobEnd);

        return $dateTime > $dtStart && $dateTime < $dtEnd;
    }

    /**
     * Возвращает следующий рабочий день.
     *
     * @param string|null $date Формат даты - "Y-m-d"
     * @return string
     * @throws \Exception
     */
    public function nextWorkingDay($date = null)
    {
        if (null === $date) {
            $date = $this->dateTime->format('Y-m-d');
        }
        $dateTime = new DateTime($date);

        do {
            $dateTime->modify('+1 day');
        } while (!$this->isWorkingDate($dateTime->format('Y-m-d')));

        return $dateTime->format('Y-m-d');
    }

    /**
     * Возвращает ближайшее рабочее время. Либо null если текущее время уже рабочее.
     *
     * @param string|null $date
     * @return null|string
     * @throws \Exception
     */
    public function nextWorkingTime($date = null)
    {
        $dateTime = $this->buildDate($date);
        $nextWorkingTime = null;

        // Если дня нет в конфиге считаем его выходным
        if (!array_key_exists($dateTime->format('w'), $this->workingDays)) {
            $nextWorkingDay = $this->nextWorkingDay($dateTime->format('Y-m-d'));
            $nWDateTime = new DateTime($nextWorkingDay);
            $workTime = explode('-', $this->workingDays[$nWDateTime->format('w')]);

            return $nextWorkingDay . ' ' . $workTime[0];
        }

        list($jobStart, $jobEnd) = explode('-', $this->workingDays[$dateTime->format('w')]);

        if ($this->isWorkingDate($dateTime->format('Y-m-d'))) { // Если день рабочий проверяем время

            $dtStart = new DateTime($dateTime->format('Y-m-d') . ' ' . $jobStart);
            $dtEnd = new DateTime($dateTime->format('Y-m-d') . ' ' . $jobEnd);

            // Если начало дня еще не наступило (утро) возвращаем указанную дату + время
            if ($dateTime < $dtStart) {
                $nextWorkingTime = $dateTime->format('Y-m-d') . ' ' . $jobStart;
            } elseif ($dateTime >= $dtEnd) { // Если рабочий день уже закончился
                // Ищем следующий рабочий день и выводим его + время начало дня
                $nextWorkingTime = $this->nextWorkingDay($dateTime->format('Y-m-d')) . ' ' . $jobStart;
            }
        } else { // Если день не рабочий

            // Ищем следующий рабочий день и выводим его + время начало дня
            $nextWorkingTime = $this->nextWorkingDay($dateTime->format('Y-m-d')) . ' ' . $jobStart;
        }

        return $nextWorkingTime;
    }

    /**
     * Возвращает дату время начала следующего дня.
     *
     * @param string|null $date
     * @return string
     * @throws \Exception
     */
    public function nextWorkingDayStart($date = null)
    {
        $nextWorkingDayDT = new DateTime($this->nextWorkingDay($date));
        $day = $nextWorkingDayDT->format('w');
        $jobStart = explode('-', $this->workingDays[$day])[0];

        return $nextWorkingDayDT->format('Y-m-d') . ' ' . $jobStart;
    }

    /**
     * Возвращает дату время начала следующего дня.
     *
     * @param string|null $date
     * @return string
     * @throws \Exception
     */
    private function nextWorkingDayEnd($date = null)
    {
        $nextWorkingDayDT = new DateTime($this->nextWorkingDay($date));
        $day = $nextWorkingDayDT->format('w');
        $jobEnd = explode('-', $this->workingDays[$day])[1];

        return $nextWorkingDayDT->format('Y-m-d') . ' ' . $jobEnd;
    }

    /**
     * Возвращает длинну рабочего дня в минутах.
     *
     * @param string|null $date Формат даты - "Y-m-d"
     * @return int
     * @throws \Exception
     */
    public function getJobMinutesInDay($date = null)
    {
        $day = $this->buildDataPartString($date, 'w');
        $nextWorkingTime = $this->nextWorkingTime($date ?: $this->dateTime->format('Y-m-d H:i'));

        list($jobStart, $jobEnd) = explode('-', $this->workingDays[$day]);
        // Считаем остаток рабочего времени
        if ($nextWorkingTime === null) {
            $jobStart = ($date === null ? date('H:i', $this->dateTime->getTimestamp()) : date('H:i', strtotime($date)));
        }

        $dtStart = new DateTime($jobStart);
        $dtEnd = new DateTime($jobEnd);
        $diff = $dtEnd->diff($dtStart);

        return ($diff->h * 60 + $diff->i);
    }

    /**
     * Прибавляет заданное количество минут к дате с учетом рабочего времени.
     *
     * @param int $minutes
     * @param string $date
     * @return DateTime
     * @throws \Exception
     */
    private function modifyDate($minutes, $date)
    {
        $dateTime = new DateTime($date);
        $jobMinutesInDay = $this->getJobMinutesInDay($date);

        // Если длинна дня больше чем время модификации
        if ($jobMinutesInDay > $minutes) {
            $dateTime->modify("+$minutes minutes");
        } else { // Если длинна дня меньше чем время модификации
            do {
                $dateTime->modify("+$jobMinutesInDay minutes");
                $minutes -= $jobMinutesInDay;
                $nextWorkingTime = $this->nextWorkingTime($dateTime->format('Y-m-d H:i'));
                $dateTime = new DateTime($nextWorkingTime);
                $jobMinutesInDay = $this->getJobMinutesInDay($dateTime->format('Y-m-d H:i'));
                if ($jobMinutesInDay > $minutes) {
                    $dateTime->modify("+$minutes minutes");
                    $minutes = 0;
                }
            } while ($minutes > 0);
        }

        return $dateTime;
    }

    /**
     * Прибавляет заданное количество минут к дате с учетом рабочего времени.
     *
     * @param int $minutes
     * @param string $date
     * @return string
     * @throws \Exception
     */
    public function modify($minutes, $date = null)
    {
        $nextWorkingTime = $this->nextWorkingTime($date);
        // Если дата вне рабочего времени
        if ($nextWorkingTime !== null) {
            $dateTime = $this->modifyDate($minutes, $nextWorkingTime);
        } else { // если дата в пределах рабочего времени
            $dateTime = $this->modifyDate($minutes, $date ?: $this->dateTime->format('Y-m-d H:i'));
        }

        if (null === $date) {
            $this->dateTime->setTimestamp($dateTime->getTimestamp());
        }

        return $dateTime->format('Y-m-d H:i');
    }

    /**
     * Возвращает рабочее время в минутах в заданном временном интервале.
     *
     * @param string $startDate
     * @param string $endDate
     * @return int
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function calculatingWorkingTime($startDate, $endDate)
    {
        if (!self::validateDate($startDate) || !self::validateDate($endDate)) {
            throw new \InvalidArgumentException("Date isn't a valid date. Dates should be formatted as Y-m-d H:i:s, e.g. `2016-10-27 17:30:00`.");
        }

        // Проверяем находится ли промежуток до наступления рабочего времени
        $nextWorkingTime = $this->nextWorkingTime($startDate);
        if(null === $nextWorkingTime) {
            $nextWorkingTime = $startDate;
        }

        $dtStart = $this->buildDate($nextWorkingTime);
        $dtEnd = $this->buildDate($endDate);

        if ((null !== $nextWorkingTime) && $dtStart > $dtEnd) {
            return 0;
        }

        $diff = $dtEnd->diff($dtStart);
        $diffMinutes = $diff->d * 1440 + $diff->h * 60 + $diff->i; // Разница между датами в минутах
        $jobMinutesInDay = $this->getJobMinutesInDay($dtStart->format('Y-m-d H:i')); // Длинна рабочего дня

        // Если разница во времени меньше длинны рабочего дня
        if ($diffMinutes < $jobMinutesInDay) {
            return $diffMinutes;
        }

        // Если разница больше то перебираем дни
        $nextWorkingDayStartDT = new DateTime($this->nextWorkingDayStart($dtStart->format('Y-m-d')));
        $nextWorkingDayEndDT = new DateTime($this->nextWorkingDayEnd($dtStart->format('Y-m-d')));
        do {
            // Дата находится в промежутке ДО наступления следующего рабочего времени
            if ($nextWorkingDayStartDT > $dtEnd) {
                return $jobMinutesInDay; // Возвращает остатки рабочего времени
            }
            // Дата в промежутке следующего рабочего дня
            if ($nextWorkingDayStartDT < $dtEnd && $dtEnd < $nextWorkingDayEndDT && ($nextWorkingDayStartDT->format('Y-m-d') === $dtEnd->format('Y-m-d'))) {
                $nextDiff = $dtEnd->diff($nextWorkingDayStartDT);
                $nextDiffMinutes = $nextDiff->d * 1440 + $nextDiff->h * 60 + $nextDiff->i;

                return $nextDiffMinutes + $jobMinutesInDay;
            }

            $jobMinutesInDay += $this->getJobMinutesInDay($nextWorkingDayStartDT->format('Y-m-d H:i')); // Длинна рабочего дня
            $nextWorkingDayStartDT = new DateTime($this->nextWorkingDayStart($nextWorkingDayStartDT->format('Y-m-d')));
            $nextWorkingDayEndDT = new DateTime($this->nextWorkingDayEnd($nextWorkingDayStartDT->format('Y-m-d')));
        } while (true);
    }

    /**
     * Проверяет является ли строка корректной датой.
     *
     * @param $date
     * @param string $format
     * @return bool
     */
    public static function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $vDate = DateTime::createFromFormat($format, $date);
        return $vDate && $vDate->format($format) === $date;
    }
}
