<?php

namespace Symbiote\Watch;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * @author marcus
 */
class ItemWatch extends DataObject
{
    private static string $table_name = 'ItemWatch';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'Type' => 'Varchar',
        'WatchData' => 'Text',
    ];

    private static array $has_one = [
        'Watched' => DataObject::class,
        'Owner' => Member::class,
    ];

    protected $watchedItem;

    public function getWatchedItem(): ?DataObject
    {
        if (!$this->watchedItem) {
            $this->watchedItem = $this->Watched();
        }

        return $this->watchedItem;
    }

    public function watch(DataObject $item, string $type = 'watch', ?Member $member = null): ?ItemWatch
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        $filter = [
            'WatchedClass' => $item->ClassName,
            'WatchedID' => $item->ID,
            'OwnerID' => $member->ID,
            'Type' => $type,
        ];

        $existing = ItemWatch::get()->filter($filter)->first();

        if ($existing) {
            return $existing;
        }

        if (!$item->canView()) {
            return null;
        }

        $this->update($filter);

        $this->Title = $item->Title . ' watched by ' . $member->getTitle();

        return $this;
    }

    public function summaryFields(): array
    {
        $fields = parent::summaryFields();
        $fields['ItemOverview'] = 'ItemOverview';
        return $fields;
    }

    public function getItemOverview(): string
    {
        return $this->renderWith([
            $this->WatchedClass . '_watchoverview',
            'ItemWatch_watchoverview'
        ]);
    }
}
