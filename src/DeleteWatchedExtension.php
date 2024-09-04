<?php

namespace Symbiote\Watch;

use Exception;
use SilverStripe\Core\Extension;
use SilverStripe\Versioned\Versioned;

class DeleteWatchedExtension extends Extension
{
    public function onAfterDelete()
    {
        if (Versioned::get_stage() === Versioned::DRAFT) {
            // find all items being watched
            $watches = ItemWatch::get()->filter([
                "WatchedClass" => get_class($this->owner),
                "WatchedID" => $this->owner->ID,
            ]);
            try {
                foreach ($watches as $watch) {
                    $watch->delete();
                }
            } catch (Exception $e) {

            }

        }
    }
}
