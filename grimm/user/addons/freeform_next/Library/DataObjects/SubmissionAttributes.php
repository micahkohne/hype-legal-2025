<?php

namespace Solspace\Addons\FreeformNext\Library\DataObjects;

use DateTime;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Form;
use Solspace\Addons\FreeformNext\Model\StatusModel;
use Solspace\Addons\FreeformNext\Model\SubmissionModel;

class SubmissionAttributes
{
    /** @var int */
    private $siteId;

    /** @var int */
    private $submissionId;

    /** @var string */
    private $token;

    /** @var int */
    private $limit;

    /** @var int */
    private $offset;

    /** @var string */
    private $orderBy;

    private ?string $sort = null;

    /** @var string */
    private $status;

    private ?string $dateRangeStart = null;

    /** @var DateTime */
    private $dateRangeEnd;

    private array $filters;

    private array $orFilters;

    private array $likeFilters;

    private array $orLikeFilters;

    private array $idFilters;

    private array $orIdFilters;

    private array $inFilters;

    private array $notInFilters;

    private array $where;

    /**
     * SubmissionAttributes constructor.
     *
     * @param Form $form
     */
    public function __construct(private readonly Form $form)
    {
        $this->filters = [
            SubmissionModel::TABLE . '.formId' => $this->form->getId(),
        ];

        $this->orFilters     = [];
        $this->likeFilters   = [];
        $this->orLikeFilters = [];
        $this->idFilters     = [];
        $this->orIdFilters   = [];
        $this->inFilters     = [];
        $this->notInFilters  = [];
        $this->where         = [];
    }

    /**
     * @return int
     */
    public function getSiteId()
    {
        return $this->siteId;
    }

    /**
     * @param int $siteId
     *
     * @return $this
     */
    public function setSiteId($siteId = null): static
    {
        $this->siteId = $siteId;

        return $this;
    }

    /**
     * @return Form
     */
    public function getForm(): Form
    {
        return $this->form;
    }

    /**
     * @return int
     */
    public function getSubmissionId()
    {
        return $this->submissionId;
    }

    /**
     * @param int $submissionId
     *
     * @return $this
     */
    public function setSubmissionId($submissionId = null): static
    {
        $this->submissionId = $submissionId;
        $this->setFilter(SubmissionModel::TABLE . '.id', $submissionId);

        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     *
     * @return SubmissionAttributes
     */
    public function setToken($token = null): static
    {
        $this->token = $token;
        $this->setFilter(SubmissionModel::TABLE . '.token', $token);

        return $this;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function setLimit($limit = null): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param int $offset
     *
     * @return $this
     */
    public function setOffset($offset = null): static
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @return string
     */
    public function getOrderBy()
    {
        $orderBy = $this->orderBy;

        if (null === $orderBy || false === $orderBy) {
            return 'dateCreated';
        }

        if ($orderBy === 'status') {
            return 'statusName';
        }

        if ($orderBy === 'date') {
            return 'dateCreated';
        }

        if ($orderBy && !in_array($orderBy, ['id', 'title', 'status'], true)) {
            foreach ($this->form->getLayout()->getFields() as $field) {
                if ($orderBy === $field->getHandle()) {
                    return SubmissionModel::getFieldColumnName($field->getId());
                }
            }
        }

        return $this->orderBy;
    }

    /**
     * @param string $orderBy
     *
     * @return $this
     */
    public function setOrderBy($orderBy = null): static
    {
        $this->orderBy = $orderBy;

        return $this;
    }

    /**
     * @return string
     */
    public function getSort(): ?string
    {
        return $this->sort;
    }

    /**
     * @param string $sort
     *
     * @return $this
     */
    public function setSort($sort = null): static
    {
        $this->sort = strtolower((string) $sort) === 'desc' ? 'DESC' : 'ASC';

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     *
     * @return $this
     */
    public function setStatus($status = null): static
    {
        $this->status = $status;
        $this->setFilter(StatusModel::TABLE . '.name', $status);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDateRangeStart(): ?string
    {
        return $this->dateRangeStart;
    }

    /**
     * @param DateTime $dateRangeStart
     *
     * @return $this
     */
    public function setDateRangeStart($dateRangeStart): static
    {
        $dateRangeStart = $this->getDateValue($dateRangeStart);

        if ($dateRangeStart) {
            $this->dateRangeStart = $dateRangeStart;
            $this->setFilter(SubmissionModel::TABLE . '.dateCreated >=', $dateRangeStart);
        }

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDateRangeEnd()
    {
        return $this->dateRangeEnd;
    }

    /**
     * @param DateTime $dateRangeEnd
     *
     * @return $this
     */
    public function setDateRangeEnd($dateRangeEnd): static
    {
        if ($dateRangeEnd) {
            $dateRangeEnd = str_replace('00:00:00', '23:59:59', $this->getDateValue($dateRangeEnd));

            $this->dateRangeEnd = $dateRangeEnd;
            $this->setFilter(SubmissionModel::TABLE . '.dateCreated <=', $dateRangeEnd);
        }

        return $this;
    }

    /**
     * @param string $string
     *
     * @return $this
     */
    public function setDateRange($string): static
    {
        if (null === $string) {
            return $this;
        }

        switch (strtolower($string)) {
            case 'today':
                $start = new DateTime();
                $start->setTime(0, 0, 0);

                $end = clone $start;
                $end->setTime(23, 59, 59);

                $this
                    ->setDateRangeStart($start)
                    ->setDateRangeEnd($end);

                break;

            case 'this week':
                $day   = date('w');
                $start = date('Y-m-d 00:00:00', strtotime('-' . $day . ' days'));
                $end   = date('Y-m-d 23:59:59', strtotime('+' . (6 - $day) . ' days'));

                $this
                    ->setDateRangeStart($start)
                    ->setDateRangeEnd($end);

                break;

            case 'this month':
                $maxDays = date('t');
                $start   = date('Y-m-01 00:00:00');
                $end     = date('Y-m-' . $maxDays . ' 23:59:59');

                $this
                    ->setDateRangeStart($start)
                    ->setDateRangeEnd($end);

                break;

            case 'last month':
                $timeLastMonth = strtotime('last month');

                $maxDays = date('t', $timeLastMonth);
                $start   = date('Y-m-01 00:00:00', $timeLastMonth);
                $end     = date('Y-m-' . $maxDays . ' 23:59:59', $timeLastMonth);

                $this
                    ->setDateRangeStart($start)
                    ->setDateRangeEnd($end);

                break;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getLikeFilters(): array
    {
        return $this->likeFilters;
    }

    /**
     * @return array
     */
    public function getOrLikeFilters(): array
    {
        return $this->orLikeFilters;
    }

    /**
     * @return array
     */
    public function getIdFilters(): array
    {
        return $this->idFilters;
    }

    /**
     * @return array
     */
    public function getOrIdFilters(): array
    {
        return $this->orIdFilters;
    }

    /**
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return array
     */
    public function getOrFilters(): array
    {
        return $this->orFilters;
    }

    /**
     * @return array
     */
    public function getInFilters(): array
    {
        return $this->inFilters;
    }

    /**
     * @return array
     */
    public function getNotInFilters(): array
    {
        return $this->notInFilters;
    }

    /**
     * @return array
     */
    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function addFilter($key, $value): static
    {
        if (false !== $this->getNotInArray($value)) {
            $this->notInFilters[$key] = $value;
        } else if (false !== $this->getInArray($value)) {
            $this->inFilters[$key] = $value;
        } else {
            $this->filters[$key] = $value;
        }

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function addOrFilter($key, $value): static
    {
        $this->orFilters[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function addLikeFilter($key, $value): static
    {
        $this->likeFilters[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function addOrLikeFilter($key, $value): static
    {
        $this->orLikeFilters[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function addIdFilter($key, $value): static
    {
        $this->idFilters[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function addOrIdFilter($key, $value): static
    {
        $this->orIdFilters[$key] = $value;

        return $this;
    }

    /**
     * @param $value
     *
     * @return $this
     */
    public function addWhere($value): static
    {
        $this->where[] = $value;

        return $this;
    }

    /**
     * @param string $string
     *
     * @return array|bool
     */
    private function getInArray($string)
    {
        if (false !== $this->getNotInArray($string)) {
            return false;
        }

        if (!str_contains($string, '|')) {
            return false;
        }

        return explode('|', $string);
    }

    /**
     * @param string $string
     *
     * @return array|bool
     */
    private function getNotInArray($string)
    {
        if (!str_starts_with($string, 'not ')) {
            return false;
        }

        $string = substr($string, 4);

        return explode('|', $string);
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    private function setFilter(string $key, $value): void
    {
        unset($this->filters[$key], $this->inFilters[$key], $this->notInFilters[$key]);

        if (null === $value) {
            return;
        }

        if (false !== $this->getNotInArray($value)) {
            $this->notInFilters[$key] = $this->getNotInArray($value);
        } else if (false !== $this->getInArray($value)) {
            $this->inFilters[$key] = $this->getInArray($value);
        } else {
            $this->filters[$key] = $value;
        }
    }

    /**
     * Takes a string or DateTime intsance and returns a
     * 'Y-m-d H:i:s' string of that date
     *
     * @param DateTime|string $date
     *
     * @return string|null
     */
    private function getDateValue($date): ?string
    {
        if (null === $date) {
            return null;
        }

        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', strtotime($date));
    }
}
