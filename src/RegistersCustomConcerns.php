<?php

namespace Bhagat\Excel;

use Bhagat\Excel\Events\AfterSheet;
use Bhagat\Excel\Events\BeforeExport;
use Bhagat\Excel\Events\BeforeSheet;
use Bhagat\Excel\Events\BeforeWriting;
use Bhagat\Excel\Events\Event;

trait RegistersCustomConcerns
{
    /**
     * @var array
     */
    private static $eventMap = [
        BeforeWriting::class => Writer::class,
        BeforeExport::class  => Writer::class,
        BeforeSheet::class   => Sheet::class,
        AfterSheet::class    => Sheet::class,
    ];

    /**
     * @param  string  $concern
     * @param  callable  $handler
     * @param  string  $event
     */
    public static function extend(string $concern, callable $handler, string $event = BeforeWriting::class)
    {
        /** @var HasEventBus $delegate */
        $delegate = static::$eventMap[$event] ?? BeforeWriting::class;

        $delegate::listen($event, function (Event $event) use ($concern, $handler) {
            if ($event->appliesToConcern($concern)) {
                $handler($event->getConcernable(), $event->getDelegate());
            }
        });
    }
}
