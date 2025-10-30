<?php

namespace Solspace\Addons\FreeformNext\Model;

use DateTime;

/**
 * Trait TimestampableTrait
 *
 * @property DateTime $dateCreated
 * @property DateTime $dateUpdated
 */
trait TimestampableTrait
{
    /** @var array */
    protected static $_events = ['beforeInsert', 'beforeUpdate'];

    /** @var DateTime */
    protected $dateCreated;

    /** @var DateTime */
    protected $dateUpdated;

    /**
     * Event beforeInsert sets the $dateCreated and $dateUpdated properties
     */
    public function onBeforeInsert(): void
    {
        $this->set(
            [
                'dateCreated' => $this->getTimestampableDate(),
                'dateUpdated' => $this->getTimestampableDate(),
            ]
        );
    }

    /**
     * Event beforeUpdate sets the $dateUpdated property
     */
    public function onBeforeUpdate(): void
    {
        $this->set(['dateUpdated' => $this->getTimestampableDate()]);
    }

    /**
     * @return DateTime
     */
    private function getTimestampableDate(): string
    {
        return date('Y-m-d H:i:s');
    }
}
