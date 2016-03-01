<?php

namespace modules\mlist;

use Cron\CronExpression;
use modules\mlist\module;

class cron {
    
    public function run() {

        $minute = CronExpression::factory('* * * * *');
        if ($minute->isDue()) {
            $l = new module();
            
        }
    }
}
