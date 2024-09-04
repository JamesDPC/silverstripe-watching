<?php

namespace Symbiote\Watch;

use Symbiote\Watch\ItemWatch;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;

/**
 * @author marcus
 */
class WatchService
{
    public function webEnabledMethods()
    {
        return [
            'subscribe'		=> 'POST',
            'list'		    => 'GET',
            'unsubscribe'	=> 'POST',
        ];
    }

    public function subscribe(DataObject $item, $type = 'watch')
    {
        if ($item->canView()) {
            $current = $this->list($item, $type);
            if (count($current)) {
                return $current->first();
            }

            $watch = ItemWatch::create()->watch($item, $type);
            $watch->write();
            return $watch;
        }
    }

    public function unsubscribe(DataObject $item)
    {
        $member = Member::currentUser();
        if (!$member) {
            return false;
        }
        if ($item->canView()) {
            $watch = ItemWatch::get()->filter([
                'OwnerID'		=> $member->ID,
                'WatchedClass'	=> get_class($item),
                'WatchedID'		=> $item->ID
            ])->first();
            if ($watch) {
                $watch->delete();
                return $watch;
            }
        }
    }

    /**
     * List all the watches a user has, on a particular item,
     * and/or of a particular type
     */
    public function list(DataObject $item = null, $type = null)
    {
        $member = Member::currentUser();
        if (!$member) {
            return new ArrayList();
        }

        $filter = [
            'OwnerID' => $member->ID,
        ];

        if ($type) {
            $filter['Type'] = $type;
        }

        if ($item && $item->canView()) {
            $filter['WatchedClass'] = get_class($item);
            $filter['WatchedID'] = $item->ID;
        }

        return ItemWatch::get()->filter($filter);
    }

    public function watchedItemsOfType($type, $member = null)
    {
        $member = $member ?: Member::currentUser();
        if (!$member) {
            return [];
        }

        $items = ItemWatch::get()->filter([
            'OwnerID'			=> $member->ID,
            'WatchedClass'		=> $type,
        ]);

        $ids = $items->column('WatchedID');
        if (count($ids)) {
            return $type::get()->filter('ID', $ids)->filterByCallback(function ($item) {
                return $item->canView();
            });
        }
    }

    public function watchersOf(DataObject $item, $type = null)
    {
        $filter = [
            'WatchedClass' => get_class($item),
            'WatchedID' => $item->ID,
        ];

        if ($type) {
            $filter['Type'] = $type;
        }

        $watches = ItemWatch::get()->filter($filter);
        $watchers = [];
        foreach ($watches as $watch) {
            $watchers[] = $watch->Owner();
        }
        return $watchers;
    }

    public function mostWatchedItems($filterBy = [], $number = 10)
    {
        $list = ItemWatch::get();
        if (count($filterBy)) {
            $list = $list->filter($filterBy);
        }

        $dataQuery = $list->dataQuery();
        $query = $dataQuery->getFinalisedQuery();

        $out = $query
            ->aggregate('COUNT("ID")', 'NumWatches')
            ->addSelect("WatchedID", "WatchedClass")
            ->setOrderBy('"NumWatches" DESC')
            ->addGroupBy(['WatchedID', 'WatchedClass'])
            // need to do twice the number here, because the limit
            // gets applied before the group because SilverStripe or something. Sigh
            ->setLimit($number * 2)
            ->execute();

        $objects = ArrayList::create();
        foreach ($out as $row) {
            $type = $row['WatchedClass'];
            $object = DataList::create($type)->byID($row['WatchedID']);
            if ($object && $object->canView()) {
                $objects->push($object);
            }
            if ($objects->count() >= $number) {
                break;
            }
        }
        return $objects;
    }
}
