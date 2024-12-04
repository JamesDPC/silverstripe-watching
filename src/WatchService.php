<?php

namespace Symbiote\Watch;

use Symbiote\Watch\ItemWatch;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;

/**
 * @author marcus
 */
class WatchService
{
    public function webEnabledMethods(): array
    {
        return [
            'subscribe'		=> 'POST',
            'list'		    => 'GET',
            'unsubscribe'	=> 'POST',
        ];
    }

    public function subscribe(DataObject $item, string $type = 'watch'): ?ItemWatch
    {
        if ($item->canView()) {
            $current = $this->list($item, $type);
            if (count($current) !== 0) {
                return $current->first();
            }

            $watch = ItemWatch::create()->watch($item, $type);
            $watch->write();
            return $watch;
        }

        return null;
    }

    public function unsubscribe(DataObject $item): ?ItemWatch
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return null;
        }

        if ($item->canView()) {
            $watch = ItemWatch::get()->filter([
                'OwnerID'		=> $member->ID,
                'WatchedClass'	=> $item::class,
                'WatchedID'		=> $item->ID
            ])->first();
            if ($watch) {
                $watch->delete();
                return $watch;
            }
        }

        return null;
    }

    /**
     * List all the watches a user has, on a particular item,
     * and/or of a particular type
     */
    public function list(?DataObject $item = null, ?string $type = ''): ArrayList|DataList
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return ArrayList::create();
        }

        $filter = [
            'OwnerID' => $member->ID,
        ];

        if ($type !== '') {
            $filter['Type'] = $type;
        }

        if ($item && $item->canView()) {
            $filter['WatchedClass'] = $item::class;
            $filter['WatchedID'] = $item->ID;
        }

        return ItemWatch::get()->filter($filter);
    }

    public function watchedItemsOfType(string $type, ?Member $member = null, bool $canViewFilter = true): ArrayList|DataList|null
    {
        $member = $member ?: Security::getCurrentUser();
        if (!$member) {
            return null;
        }

        $items = ItemWatch::get()->filter([
            'OwnerID'			=> $member->ID,
            'WatchedClass'		=> $type,
        ]);

        $ids = $items->column('WatchedID');
        if (count($ids) !== 0) {
            $list = $type::get()->filter('ID', $ids);
            if($canViewFilter) {
                $list = $list->filterByCallback(fn($item) => $item->canView());
            }
            return $list;
        } else {
            return null;
        }
    }

    /**
     * @return mixed[]
     */
    public function watchersOf(DataObject $item, string $type = ''): array
    {
        $filter = [
            'WatchedClass' => $item::class,
            'WatchedID' => $item->ID,
        ];

        if ($type !== '') {
            $filter['Type'] = $type;
        }

        $watches = ItemWatch::get()->filter($filter);
        $watchers = [];
        foreach ($watches as $watch) {
            $watchers[] = $watch->Owner();
        }

        return $watchers;
    }

    public function mostWatchedItems(array $filterBy = [], int $number = 10): ArrayList
    {
        $list = ItemWatch::get();
        if (count($filterBy) !== 0) {
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
