<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces;

interface ExternalOptionsInterface extends OptionsInterface
{
    public const SOURCE_CUSTOM     = 'custom';
    public const SOURCE_ENTRIES    = 'entries';
    public const SOURCE_CATEGORIES = 'categories';
    public const SOURCE_MEMBERS    = 'members';
    public const SOURCE_PREDEFINED = 'predefined';

    public const PREDEFINED_DAYS               = 'days';
    public const PREDEFINED_DAYS_OF_WEEK       = 'days_of_week';
    public const PREDEFINED_MONTHS             = 'months';
    public const PREDEFINED_NUMBERS            = 'numbers';
    public const PREDEFINED_YEARS              = 'years';
    public const PREDEFINED_PROVINCES          = 'provinces';
    public const PREDEFINED_STATES             = 'states';
    public const PREDEFINED_STATES_TERRITORIES = 'states_territories';
    public const PREDEFINED_COUNTRIES          = 'countries';
    public const PREDEFINED_LANGUAGES          = 'languages';

    /**
     * Returns the option source
     *
     * @return string
     */
    public function getOptionSource();

    /**
     * @return mixed
     */
    public function getOptionTarget();

    /**
     * @return array
     */
    public function getOptionConfiguration();
}
