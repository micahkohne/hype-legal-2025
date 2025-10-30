<?php

namespace Solspace\Addons\FreeformNext\Library\Factories;

use Solspace\Addons\FreeformNext;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\DataContainers\Option;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces\ExternalOptionsInterface;
use Solspace\Addons\FreeformNext\Library\Configuration\ExternalOptionsConfiguration;

class PredefinedOptionsFactory
{
    const TYPE_INT              = 'int';
    const TYPE_INT_LEADING_ZERO = 'int_w_zero';
    const TYPE_FULL             = 'full';
    const TYPE_ABBREVIATED      = 'abbreviated';

    /**
     * @param string                       $type
     * @param ExternalOptionsConfiguration $configuration
     * @param array                        $selectedValues
     *
     * @return Option[]
     */
    public static function create($type, ExternalOptionsConfiguration $configuration, array $selectedValues = []): array
    {
        $instance = new self($configuration, $selectedValues);

        $options = match ($type) {
            ExternalOptionsInterface::PREDEFINED_NUMBERS => $instance->getNumberOptions(),
            ExternalOptionsInterface::PREDEFINED_YEARS => $instance->getYearOptions(),
            ExternalOptionsInterface::PREDEFINED_MONTHS => $instance->getMonthOptions(),
            ExternalOptionsInterface::PREDEFINED_DAYS => $instance->getDayOptions(),
            ExternalOptionsInterface::PREDEFINED_DAYS_OF_WEEK => $instance->getDaysOfWeekOptions(),
            ExternalOptionsInterface::PREDEFINED_COUNTRIES => $instance->getCountryOptions(),
            ExternalOptionsInterface::PREDEFINED_LANGUAGES => $instance->getLanguageOptions(),
            ExternalOptionsInterface::PREDEFINED_PROVINCES => $instance->getProvinceOptions(),
            ExternalOptionsInterface::PREDEFINED_STATES => $instance->getStateOptions(),
            ExternalOptionsInterface::PREDEFINED_STATES_TERRITORIES => $instance->getStateTerritoryOptions(),
            default => [],
        };

        if ($configuration->getEmptyOption()) {
            array_unshift($options, new Option(lang($configuration->getEmptyOption()), ''));
        }

        return $options;
    }

    /**
     * PredefinedOptionsFactory constructor.
     *
     * @param ExternalOptionsConfiguration $configuration
     * @param array                        $selectedValues
     */
    private function __construct(private readonly ExternalOptionsConfiguration $configuration, private readonly array $selectedValues)
    {
    }

    /**
     * @return Option[]
     */
    private function getNumberOptions(): array
    {
        $options = [];

        $start = $this->getConfig()->getStart() ?: 0;
        $end   = $this->getConfig()->getEnd() ?: 20;
        foreach (range($start, $end) as $number) {
            $options[] = new Option($number, $number, $this->isChecked($number));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getYearOptions(): array
    {
        $options = [];

        $currentYear = (int) date('Y');
        $start       = $this->getConfig()->getStart() ?: 100;
        $end         = $this->getConfig()->getEnd() ?: 0;
        $isDesc      = $this->getConfig()->getSort() === 'desc';

        $range = $isDesc ? range($currentYear + $end, $currentYear - $start) : range($currentYear - $start, $currentYear + $end);
        foreach ($range as $year) {
            $options[] = new Option($year, $year, $this->isChecked($year));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getMonthOptions(): array
    {
        $options = [];

        $labelFormat = self::getMonthFormatFromType($this->getConfig()->getListType());
        $valueFormat = self::getMonthFormatFromType($this->getConfig()->getValueType());
        foreach (range(0, 11) as $month) {
            $label = date($labelFormat, strtotime("january 2017 +$month month"));
            $value = date($valueFormat, strtotime("january 2017 +$month month"));

            $options[] = new Option($label, $value, $this->isChecked($value));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getDayOptions(): array
    {
        $options = [];

        $labelFormat = self::getDayFormatFromType($this->getConfig()->getListType());
        $valueFormat = self::getDayFormatFromType($this->getConfig()->getValueType());

        foreach (range(1, 31) as $dayIndex) {
            $label = $labelFormat === 'd' ? str_pad($dayIndex, 2, '0', STR_PAD_LEFT) : $dayIndex;
            $value = $valueFormat === 'd' ? str_pad($dayIndex, 2, '0', STR_PAD_LEFT) : $dayIndex;

            $options[] = new Option($label, $value, $this->isChecked($value));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getDaysOfWeekOptions(): array
    {
        $options = [];

        $firstDayOfWeek = $this->getConfig()->getStart() ?: 1;
        $labelFormat    = self::getDayOfTheWeekFormatFromType($this->getConfig()->getListType());
        $valueFormat    = self::getDayOfTheWeekFormatFromType($this->getConfig()->getValueType());
        foreach (range(0, 6) as $dayIndex) {
            $dayIndex += $firstDayOfWeek;

            $label = date($labelFormat, strtotime("Sunday +$dayIndex days"));
            $value = date($valueFormat, strtotime("Sunday +$dayIndex days"));

            $options[] = new Option($label, $value, $this->isChecked($value));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getCountryOptions(): array
    {
        /** @var array $countries */
        static $countries;
        if (null === $countries) {
            $countries = json_decode(file_get_contents(__DIR__ . '/Data/countries.json'), true);
        }

        $options      = [];
        $isShortLabel = $this->getConfig()->getListType() === self::TYPE_ABBREVIATED;
        $isShortValue = $this->getConfig()->getValueType() === self::TYPE_ABBREVIATED;
        foreach ($countries as $abbreviation => $countryName) {
            $label = $isShortLabel ? $abbreviation : $countryName;
            $value = $isShortValue ? $abbreviation : $countryName;

            $options[] = new Option($label, $value, $this->isChecked($value));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getLanguageOptions(): array
    {
        /** @var array $languages */
        static $languages;
        if (null === $languages) {
            $languages = json_decode(file_get_contents(__DIR__ . '/Data/languages.json'), true);
        }

        $options      = [];
        $isShortLabel = $this->getConfig()->getListType() === self::TYPE_ABBREVIATED;
        $isShortValue = $this->getConfig()->getValueType() === self::TYPE_ABBREVIATED;
        foreach ($languages as $abbreviation => $data) {
            $label = $isShortLabel ? $abbreviation : $data['name'];
            $value = $isShortValue ? $abbreviation : $data['name'];

            $options[] = new Option($label, $value, $this->isChecked($value));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getProvinceOptions(): array
    {
        /** @var array $provinces */
        static $provinces;
        if (null === $provinces) {
            $provinces = json_decode(file_get_contents(__DIR__ . '/Data/provinces.json'), true);
        }

        $options      = [];
        $isShortLabel = $this->getConfig()->getListType() === self::TYPE_ABBREVIATED;
        $isShortValue = $this->getConfig()->getValueType() === self::TYPE_ABBREVIATED;
        foreach ($provinces as $abbreviation => $provinceName) {
            $label = $isShortLabel ? $abbreviation : $provinceName;
            $value = $isShortValue ? $abbreviation : $provinceName;

            $options[] = new Option($label, $value, $this->isChecked($value));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getStateOptions(): array
    {
        /** @var array $states */
        static $states;
        if (null === $states) {
            $states = json_decode(file_get_contents(__DIR__ . '/Data/states.json'), true);
        }

        $options      = [];
        $isShortLabel = $this->getConfig()->getListType() === self::TYPE_ABBREVIATED;
        $isShortValue = $this->getConfig()->getValueType() === self::TYPE_ABBREVIATED;
        foreach ($states as $abbreviation => $stateName) {
            $label = $isShortLabel ? $abbreviation : $stateName;
            $value = $isShortValue ? $abbreviation : $stateName;

            $options[] = new Option($label, $value, $this->isChecked($value));
        }

        return $options;
    }

    /**
     * @return Option[]
     */
    private function getStateTerritoryOptions(): array
    {
        /** @var array $states */
        static $states;
        if (null === $states) {
            $states = json_decode(file_get_contents(__DIR__ . '/Data/states-territories.json'), true);
        }

        $options      = [];
        $isShortLabel = $this->getConfig()->getListType() === self::TYPE_ABBREVIATED;
        $isShortValue = $this->getConfig()->getValueType() === self::TYPE_ABBREVIATED;
        foreach ($states as $abbreviation => $stateName) {
            $label = $isShortLabel ? $abbreviation : $stateName;
            $value = $isShortValue ? $abbreviation : $stateName;

            $options[] = new Option($label, $value, $this->isChecked($value));
        }

        return $options;
    }


    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function isChecked($value): bool
    {
        return \in_array((string) $value, $this->selectedValues, true);
    }

    /**
     * @return ExternalOptionsConfiguration
     */
    private function getConfig(): ExternalOptionsConfiguration
    {
        return $this->configuration;
    }

    /**
     * @param string|null $type
     *
     * @return string
     */
    private static function getMonthFormatFromType($type = null): string
    {
        $format = 'F';
        $format = match ($type) {
            self::TYPE_INT => 'n',
            self::TYPE_INT_LEADING_ZERO => 'm',
            self::TYPE_ABBREVIATED => 'M',
            default => $format,
        };

        return $format;
    }

    /**
     * @param string|null $type
     *
     * @return string
     */
    private static function getDayFormatFromType($type = null): string
    {
        $format = 'd';
        $format = match ($type) {
            self::TYPE_INT, self::TYPE_ABBREVIATED => 'j',
            default => $format,
        };

        return $format;
    }

    /**
     * @param string|null $type
     *
     * @return string
     */
    private static function getDayOfTheWeekFormatFromType($type = null): string
    {
        $format = 'l';
        $format = match ($type) {
            self::TYPE_INT => 'N',
            self::TYPE_ABBREVIATED => 'D',
            default => $format,
        };

        return $format;
    }
}
