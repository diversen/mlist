<?php

namespace modules\mlist;

use diversen\log;
use diversen\mailsmtp;
use diversen\conf;
use Exception;
use Cron\CronExpression;
use modules\mlist\module;

class cron extends module {
    
    public function run() {

        $minute = CronExpression::factory('* * * * *');
        if ($minute->isDue()) {
            
            $this->sendMails();
            
            
        }
    }
    
    /**
     * Get mail_id and list_id from queue with status = 0 
     */
    public function sendMails () {

        $queue = $this->getMailsDueToSend();
        foreach($queue as $q) {
            $this->sendMailToList($q);
        }
    }
    
    /**
     * Send a single mail to all members
     * @param type $queue
     */
    public function sendMailToList ($queue) {

        $members = $this->getListMembers($queue['list']);
        foreach($members as $member) {
            try {
                $this->send($member['email'], $queue['mail']);
            } catch (Exception $e) {
                log::error($e->getMessage());
            }
            $sleep = conf::getModuleIni('mlist_sleep');
            sleep($sleep);
            
        }
    }
    
    public function send ($email, $mail_id) {
        
        $mail = $this->getMail($mail_id);
        $html = $this->getEmailHtml($mail_id);
        $txt = $this->getEmailTxt($mail_id);

        $res = mailsmtp::mail($email, $mail['subject'], $txt, $html);
        $this->generateReport($email, $mail_id, $res);
    }
}
