<?php

namespace Symbiote\Watch;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataList;
use SilverStripe\Security\Member;

/**
 * @author marcus
 */
class ItemWatch extends DataObject
{
    private static $table_name = 'ItemWatch';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Type' => 'Varchar',
        'WatchData' => 'Text',
    ];

    private static $has_one = [
        'Watched' => DataObject::class,
        'Owner' => Member::class,
    ];

    protected $watchedItem = null;

    public function getWatchedItem()
    {
        if (!$this->watchedItem) {
            $this->watchedItem = $this->Watched();
        }
        return $this->watchedItem;
    }

    public function watch($item, $type = 'watch', $member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
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

    public function summaryFields()
    {
        $fields = parent::summaryFields();
        $fields['ItemOverview'] = 'ItemOverview';
        return $fields;
    }

    public function getItemOverview()
    {
        return $this->renderWith([
            $this->WatchedClass . '_watchoverview',
            'ItemWatch_watchoverview'
        ]);
    }
}
