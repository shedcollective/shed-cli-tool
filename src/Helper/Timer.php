<?php

namespace App\Helper;

final class Timer
{
    private $fStart;
    private $fStop;

    // --------------------------------------------------------------------------

    /**
     * Start the timer
     */
    public function start()
    {
        $this->fStart = microtime(true);
    }

    // --------------------------------------------------------------------------

    /**
     * Stop the timer
     */
    public function stop()
    {
        $this->fStop = microtime(true);
    }

    // --------------------------------------------------------------------------

    /**
     * Get the duration of the timer
     *
     * @return object
     */
    public function duration()
    {
        $fStart    = $this->fStart ?: microtime(true);
        $fStop     = $this->fStop ?: microtime(true);
        $fDuration = $fStop - $fStart;

        return $fDuration;
    }

    // --------------------------------------------------------------------------

    /**
     * Reset the timer
     */
    public function reset()
    {
        $this->fStart = null;
        $this->fStop  = null;
    }
}
