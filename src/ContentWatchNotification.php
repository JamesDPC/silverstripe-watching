<?php

namespace Symbiote\Watch;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Config\Config;

class ContentWatchNotification extends DataExtension
{
    private static $watch_types = [
        Page::class => 'watch',
    ];

    /**
     * @var WatchService
     */
    public $watchService;

    /**
     * @var NotificationService
     */
    public $notificationService;

    public function onAfterPublish()
    {
        if ($this->notificationService) {
            $this->notificationService->notify(
                'CONTENT_PUBLISHED',
                $this->owner
            );

            if ($this->owner instanceof \Page && method_exists($this->owner, 'getSectionPage')) {
                $section = $this->owner->getSectionPage();
                if ($section && $section->ID != $this->owner->ID) {
                    $link = $this->owner->AbsoluteLink();
                    $this->notificationService->notify(
                        'SECTION_CONTENT_PUBLISHED',
                        $section,
                        [
                            'InnerTitle' => $this->owner->Title,
                            'InnerLink' => $link,
                            'Link' => $link,
                            'SectionLink' => $section->AbsoluteLink(),
                        ]
                    );
                }
            }
        }
    }

    public function getRecipients($identifier)
    {
        if ($this->watchService) {
            $watchers = $this->watchService->watchersOf($this->owner, $this->getWatchType());
            return $watchers;
        }
    }

    public function getWatchType()
    {
        $type = get_class($this->owner);
        $types = Config::inst()->get(ContentWatchNotification::class, 'watch_types');

        if (!isset($types[$type])) {
            $type = Page::class;
        }

        return isset($types[$type]) ? $types[$type] : '';
    }
}
