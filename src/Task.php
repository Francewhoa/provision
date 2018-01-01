<?php

namespace Aegir\Provision;

class Task {
    
    function execute($callable)
    {
        $this->callable = $callable;
        return $this;
    }
    function start($message) {

        $this->start = $message;
        return $this;
    }
    function success($message) {

        $this->success = $message;
        return $this;
    }
    function failure($message) {
        $this->failure = $message;
        return $this;
    }
}