<?php

namespace chaser\reactor;

use Throwable;

/**
 * 基于 event 扩展的事件反应类
 *
 * @package chaser\reactor
 */
class Event extends Reactor
{
    /**
     * 事件库
     *
     * @var object
     */
    protected object $eventBase;

    /**
     * 事件类
     *
     * @var string
     */
    protected string $eventClass;

    /**
     * 初始化事件库
     */
    public function __construct()
    {
        $eventBaseClass = class_exists('\\\\EventBase', false) ? '\\\\EventBase' : '\EventBase';

        $this->eventBase = new $eventBaseClass();

        $this->eventClass = class_exists('\\\\Event', false) ? '\\\\Event' : '\Event';
    }

    /**
     * @inheritDoc
     */
    public function loop(): void
    {
        $this->eventBase->loop();
    }

    /**
     * @inheritDoc
     */
    public function destroy(): void
    {
        $this->eventBase->exit();
    }

    /**
     * @inheritDoc
     */
    protected function makeReadData(int $intFd, $fd, callable $callback)
    {
        $flags = $this->eventClass::READ | $this->eventClass::PERSIST;
        $event = new $this->eventClass($this->eventBase, $fd, $flags, $callback);
        return $event && $event->add() ? $event : false;
    }

    /**
     * @inheritDoc
     */
    protected function makeWriteData(int $intFd, $fd, callable $callback)
    {
        $flags = $this->eventClass::WRITE | $this->eventClass::PERSIST;
        $event = new $this->eventClass($this->eventBase, $fd, $flags, $callback);
        return $event && $event->add() ? $event : false;
    }

    /**
     * @inheritDoc
     */
    protected function makeSignalData(int $signal, callable $callback)
    {
        $flags = $this->eventClass::SIGNAL | $this->eventClass::PERSIST;
        $event = new $this->eventClass($this->eventBase, $signal, $flags, $callback);
        return $event && $event->add() ? $event : false;
    }

    /**
     * @inheritDoc
     */
    protected function makeIntervalData(int $timerId, int $seconds, callable $callback)
    {
        $flags = $this->eventClass::TIMEOUT | $this->eventClass::PERSIST;
        $event = new $this->eventClass($this->eventBase, -1, $flags, fn() => $this->timerCallback($timerId, self::EV_INTERVAL));
        return $event && $event->add($seconds) ? [$event, $callback] : false;
    }

    /**
     * @inheritDoc
     */
    protected function makeTimeoutData(int $timerId, int $seconds, callable $callback)
    {
        $flags = $this->eventClass::TIMEOUT | $this->eventClass::PERSIST;
        $event = new $this->eventClass($this->eventBase, -1, $flags, fn() => $this->timerCallback($timerId, self::EV_TIMEOUT));
        return $event && $event->add($seconds) ? [$event, $callback] : false;
    }

    /**
     * @inheritDoc
     */
    protected function delDataModel(int $flag, int $key): bool
    {
        switch ($flag) {
            case self::EV_READ:
            case self::EV_WRITE:
            case self::EV_SIGNAL:
                return $this->events[$flag][$key]->del();
            case self::EV_INTERVAL:
            case self::EV_TIMEOUT:
                return $this->events[$flag][$key][0]->del();
        }
        return false;
    }

    /**
     * 定时器事件处理程序
     *
     * @param int $timerId
     * @param int $flag
     */
    public function timerCallback(int $timerId, int $flag)
    {
        if (isset($this->events[$flag][$timerId])) {

            [, $callback] = $this->events[$flag][$timerId];

            if ($flag === self::EV_TIMEOUT) {
                $this->delTimeout($timerId);
            }

            $callback($timerId);
        }
    }
}
