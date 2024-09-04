<?php

namespace Symbiote\Watch;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Config\Config;
use Symbiote\Notifications\Service\NotificationService;

class ContentWatchNotification extends DataExtension
{
    private static array $watch_types = [
        \Page::class => 'watch',
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
                $this->getOwner()
            );

            // TODO clarity on what getSectionPage returns, could be dead code
            if ($this->getOwner() instanceof \Page && $this->getOwner()->hasMethod('getSectionPage')) {
                $section = $this->getOwner()->getSectionPage();
                if ($section && $section->ID != $this->getOwner()->ID) {
                    $link = $this->getOwner()->AbsoluteLink();
                    $this->notificationService->notify(
                        'SECTION_CONTENT_PUBLISHED',
                        $section,
                        [
                            'InnerTitle' => $this->getOwner()->Title,
                            'InnerLink' => $link,
                            'Link' => $link,
                            'SectionLink' => $section->AbsoluteLink(),
                        ]
                    );
                }
            }
        }
    }

    public function getRecipients($identifier): array
    {
        if ($this->watchService) {
            return $this->watchService->watchersOf($this->getOwner(), $this->getWatchType());
        }
        return [];
    }

    public function getWatchType()
    {
        $type = $this->getOwner()::class;
        $types = Config::inst()->get(ContentWatchNotification::class, 'watch_types');

        if (!isset($types[$type])) {
            $type = \Page::class;
        }

        return $types[$type] ?? '';
    }
}
