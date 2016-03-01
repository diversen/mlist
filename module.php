<?php

namespace modules\mlist;

use diversen\conf;
use diversen\date;
use diversen\db\q;
use diversen\db\rb;
use diversen\html;
use diversen\http;
use diversen\log;
use diversen\mailsmtp;
use diversen\moduleloader;
use diversen\pagination;
use diversen\session;
use diversen\uri\direct;
use diversen\valid;
use Michelf\MarkdownExtra;
use R;

/**
 * You will need to install dependencies. `simple-php-classes` does 
 * not automaticaly installs dependencies. In order to uses the mail
 * wrapper, do a: 
 * 
 *     composer require michelf/php-markdown
 * 
 * and in order to install phpmailer: 
 * 
 *     composer require phpmailer/phpmailer
 * 
 * @return type
 */
class module {

    /**
     * Connect to existing DSN
     */
    public function __construct() {
        rb::connectExisting();
    }
    
    public function checkAccess () {
        if (!session::isAdmin()) {
            moduleloader::setStatus(403);
            return false;
        }
        return true;
    }
    /**
     * Get HTML template for email
     */
    public function getHtmlTemplate() {
        // Get email template
        $email = file_get_contents(conf::pathModules() . '/mlist/templates/template.html');
        return $email;
    }

    /**
     * Generates txt version from markdown, and returns the txt version
     * @return id $id the mail id
     */
    public function getEmailTxt($id) {
        
        $bean = rb::getBean($this->table, 'id', $id);

        $email = "# " . $bean->subject . PHP_EOL . PHP_EOL . $bean->email;

        $md = sys_get_temp_dir() . "/" . uniqid() . ".md";
        file_put_contents($md, $email);
        
        $txt = sys_get_temp_dir() . "/" . uniqid() . ".txt";
        
        shell_exec("pandoc $md " . " -o $txt");
        $ret = file_get_contents($txt);
        unlink($txt); unlink($md);
        return $ret;
    }

    
    /**
     * View send email form
     */
    public function formSendMail() {

        // Draw form
        $f = new html();
        $f->formStart();
        $f->legend('Send mail');
        $f->label('to', 'To');
        $f->text('to');
        $f->submit('send', 'Send mail');
        $f->formEnd();
        echo $f->getStr();
    }
    
        
    public function listAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        echo html::getHeadline('Send mail to list', 'h2');
        echo $this->viewOptions($id);
        $this->formSendToList();
        if (isset($_POST['list'])) {
            $this->addListTasks($id, $_POST['list']);
            http::locationHeader("/mlist/list/$id", 'List has been added to queue');
        }
    }
    /*
            
        $html = $this->getEmailHtml($id);
        $txt = $this->getEmailTxt($id);
        
        // if (isset($_POST['send'])) {
        $files = [];
        if (!empty($mail->exports)) {
            $files[] = $this->getPdf($id);
            $files[] = $this->getDocx($id);
        }
        
        
        $res = mailsmtp::mail($_POST['to'], $mail->subject, $txt, $html, $files);
        if ($res) {
            $this->generateReport($_POST['to'], $bean);
            http::locationHeader("/mlist/send/$id", "Mail was sent");
        }
        //}
     * 
     */
    
    public function addListTasks($id, $list) {
        echo $id;
        echo $list;
        die;
        
        // get mail
        $mail = rb::getBean($this->table, 'id', $id);
        
        // get list members
        $rows = q::select('members')->filter('list =', $list)->fetch();

        $client = new GearmanClient();
        $client->addServer();

        $i = 0;

        // Add tasks
        $results = array();
        foreach ($rows as $row) {

            $client->addTaskBackground("send_mail", json_encode($row), $results, $row['id']);
            log::debug("Adding job $i with the row id = $row[id]");
            $i++;
        }

        // Run tasks
        $client->runTasks();
    }

    /**
     * View send email form
     */
    public function formSendToList() {

        $rows = q::select('list')->fetch();
        $ary = array ();
        $ary[0] = 'Select maliling-list';
        foreach ($rows as $row) {
            $ary[$row['id']] = $row['title'];
        }
        // Draw form
        $f = new html();
        $f->init(array(), 'send');
        $f->formStart();
        
        $f->legend('Send mail to list');
        
        $f->selectAry('list', $ary);
        $f->label('send', '');
        $f->submit('send', 'Send mail to list');
        $f->formEnd();
        echo $f->getStr();
    }
    
    /**
     * View compsoe email form
     * @param array $ary values to preload the form with
     */
    public function formMail ($ary = array ()) {

        
        $f = new html();
        $f->init($ary, 'send', true);
        $f->setAutoEncode(true);
        $f->formStart();

        $f->legend('Send or create email');
        
        $f->label('subject', 'Subject');
        $f->text('subject');
        
        $f->label('exports', 'Generate exports');
        $f->checkbox('exports');
        
        $f->label('email', 'Content (Markdown)');
        $f->textarea('email', null, array ( 'class' => 'markdown', 'data-uk-htmleditor' => "{mode:'split', maxsplitsize:600, markdown:true}"));

        $f->submit('send', 'Save');
        $f->formEnd();
        $str = $f->getStr();
        
        echo $str;
        
    }

    /**
     * /create create action
     */
    public function createAction() {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $bean = R::dispense($this->table);
        $bean->subject = 'Subject';
        $bean->email = 'Email';
        $bean->created = date::getDateNow(array('hms' => true));
        
        $res = rb::commitBean($bean);
        if ($res) {
            $localtion = "/mlist/edit/$res";
            http::locationHeader($localtion, 'Mail was created');
        } else {
            die('Could not create mail');
        }
        
    }
    
    /**
     * / index action
     */
    public function indexAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $per_page = 10;
        $num_rows = q::numRows('mail')->fetch();
        $p = new pagination($num_rows, $per_page);
        
        $rows = q::select('mail')->order('created', 'DESC')->limit($p->from, $per_page)->fetch();
        $this->viewMailRows($rows);
        echo $p->echoPagerHTML(); 
    }
    
    /**
     * View mail rows 
     * @param array $rows the rows to display
     */
    public function viewMailRows ($rows) {
        foreach ($rows as $row) {
            echo html::getHeadline($row['subject'], 'h2');
            echo $this->viewOptions($row['id']);
        }
    }
    
    /**
     * View mail options
     * @param array $row
     * @return string $html
     */
    public function viewOptions($id) {
        $str = '<ul class="uk-subnav">';
        $str.= '<li>' . html::createLink("/mlist/send/$id", 'Send single') . '</li>';
        $str.= '<li>' . html::createLink("/mlist/list/$id", 'Send to list') . '</li>';
        $str.= '<li>' . html::createLink("/mlist/view/$id", 'View') . '</li>';
        $str.= '<li>' . html::createLink("/mlist/edit/$id", 'Edit') . '</li>';
        $str.= '<li>' . html::createLink("/mlist/delete/$id", 'Delete') . '</li>';
        $str.= '</ul>';
        return $str;
        
    }
    
    /**
     * @var string $table the mail table
     */
    public $table = 'mail';
    
    /**
     * /delete delete action
     */
    public function deleteAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        if (isset($_POST['delete'])) {
            
            $bean = rb::getBean($this->table, 'id', $id);
            R::trash($bean);
            http::locationHeader('/mlist/index');
        }
        
        echo html::getHeadline('Delete mail', 'h2');
        echo $this->viewOptions($id);
        
        $f = new html();
        $f->formStart();
        $f->legend('Delete mail');
        $f->submit('delete', 'Delete');
        $f->formEnd();
        echo $f->getStr();
    }
    
    /**
     * Get HTML mail from ID
     * @param int $id
     * @return string $html
     */
    public function getEmailHtml ($id) {
        
        $bean = rb::getBean($this->table, 'id', $id);
        $template = $this->getHtmlTemplate();
        $email = "# " . $bean->subject . PHP_EOL . PHP_EOL . $bean->email;
        $email = MarkdownExtra::defaultTransform($email);
        $subject = html::specialEncode($bean->subject);
        $str = str_replace(array('{title}', '{content}'), array ($subject, $email), $template);
        return $str;
    }
    
    /**
     * /view View HTML mail action 
     */
    public function viewAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        $str = $this->getEmailHtml($id);
        echo $str; die;
    }
    
        
    /**
     * /txt View txt version
     */
    public function txtAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        $str = $this->getEmailTxt($id);
        echo $str; die;
    }

       
    /**
     * Get PDF version as a file
     * @param int $id the mail id
     * @return string $path the path to the pdf file
     */
    public function getPdf($id) {
        
        $bean = rb::getBean($this->table, 'id', $id);
        $email = "# " . $bean->subject . PHP_EOL . PHP_EOL . $bean->email;
        
        $md = sys_get_temp_dir() . "/" . uniqid() . ".md";
        file_put_contents($md, $email);
        
        $pdf = sys_get_temp_dir() . "/" .$bean->subject . ".pdf";
        $template = "--template=" . conf::pathBase() . "/templates/my.latex";

        shell_exec("pandoc $template -S --toc -V fontsize=12pt -V lang=danish $md -o $pdf");
        unlink($md);
        return $pdf;
    }

    /**
     * Get docx version as a file
     * @param int $id the mail id
     * @return string $path the path to the docx file
     */
    public function getDocx($id) {
        
        $bean = rb::getBean($this->table, 'id', $id);
        $email = "# " . $bean->subject . PHP_EOL . PHP_EOL . $bean->email;
        
        $md = sys_get_temp_dir() . "/" . uniqid() . ".md";
        file_put_contents($md, $email);
        
        $docx = sys_get_temp_dir() . "/" .$bean->subject . ".docx";
        shell_exec("pandoc $md " . " -o $docx");
        unlink($md);
        return $docx;
    }
    
    /**
     * /send Send action
     */
    public function sendAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        $bean = rb::getBean($this->table, 'id', $id);
        
        $html = $this->getEmailHtml($id);
        $txt = $this->getEmailTxt($id);
        
        if (isset($_POST['send'])) {
            $files = [];
            if (!empty($bean->exports)) {
                $files[] = $this->getPdf($id);
                $files[] = $this->getDocx($id);
            }

            $res = mailsmtp::mail($_POST['to'], $bean->subject, $txt, $html, $files);
            if ($res) {
                $this->generateReport($_POST['to'], $bean);
                http::locationHeader("/mlist/send/$id", "Mail was sent");
            }
        }

        echo html::getHeadline('Send single email', 'h2');
        echo $this->viewOptions($id);
        $this->formSendMail();
        $this->viewReport($id);
        
    }
    
    public function formCreateList () {
        
        $f = new html();
        // $f->init(array(), null, true);
        $f->formStart();
        
        $f->legend('Create list');
        $f->text('list');
        $f->submit('create', 'Create new list');
        $f->formEnd();
        echo $f->getStr();
    }
    
    public function listsAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        if (isset($_POST['create']) && isset($_POST['list']) && !empty($_POST['list'])) {
            $bean = R::dispense('list');
            $bean->title = $_POST['list'];
            $bean->date = date::getDateNow(array('hms' => true));
            R::store($bean);
            http::locationHeader('/mlist/lists', 'List was created');
        }
        
        echo html::getHeadline('Create list', 'h2');
        $this->formCreateList();
        $this->viewLists();

    }
    
    public function membersAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        $row = q::select('list')->filter('id =', $id)->fetchSingle();
        $row = html::specialEncode($row);
        
        echo html::getHeadline("Edit list: $row[title]", 'h2');
        echo html::createLink('/mlist/lists', "Go back to lists");
        $this->formListAdd($id);
        
        if (isset($_POST['add'])) {
            $this->updateMembers();
        }
    }
    
    public function updateMembers() {

        $members = explode(PHP_EOL, $_POST['members']);

        R::begin();
        q::delete('members')->filter('list =', $_POST['id'])->exec();
        foreach ($members as $member) {
            $member = trim($member);
            if ($this->validateMail($member)) {
                $bean = R::dispense('members');
                $bean->list = $_POST['id'];
                $bean->email = $member;
                R::store($bean);
            }
        }
        http::locationHeader("/mlist/members/$_POST[id]", "List was updated");

    }

    public function formListAdd ($id) {
        $rows = q::select('members')->filter('list =', $id)->fetch();
        $str = '';
        foreach($rows as $row) {
            $str.= $row['email'] . PHP_EOL;
        }

        $f = new html();

        $f->formStart();
        $f->legend('Add emails to list. New emails on newline');
        $f->hidden('id', $id);
        $f->textarea('members', $str, array ('cols' => '80'));
        $f->submit('add', 'Add');
        $f->formEnd();
        echo $f->getStr();
    }
    
    
    public function viewLists () {        
        $rows = q::select('list')->fetch();
        
        $m = new menu();
        foreach($rows as $row) {
            echo html::getHeadline("$row[title]</b> ($row[date])<br />", "h4");
            
            $ary = [];
            $ary[] = array ('url' => "/mlist/members/$row[id]", 'title' => 'Edit list');
            $ary[] = array ('url' => "/mlist/deletelist/$row[id]", 'title' => 'Delete list');
            $str = '<ul class="uk-subnav">';
            $str.= $m->getSubNav($ary);
            $str.= '</ul>';
            echo $str;
            
            echo "<hr />";
        }
    }
    
    public function deletelistAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        echo html::getHeadline('Delete list', 'h2');
        
        $id = direct::fragment(2);
        $f = new html();
        $f->formStart();
        $f->legend('Delete list');
        $f->submit('delete', 'Delete');
        $f->formEnd();
        echo $f->getStr();
        
        if (isset($_POST['delete'])) {
            $this->deleteList();
            http::locationHeader('/mlist/lists', 'List has been deleted');
        }
    }
    
    private function deleteList () {
        $id = direct::fragment(2);
        q::delete('list')->filter('id =', $id)->exec();
        q::delete('members')->filter('list =', $id)->exec();
        return;
    }

    
    /**
     * Displays a report of whom has been sent an email
     * @param int $id the mail id
     */
    public function viewReport($id) {
        $rows = q::select('report')->filter('parent =', $id)->fetch();
        echo html::getHeadline("This email has been sent to", 'h2');
        foreach($rows as $row) {

            echo $row['to'];
            echo "<hr />";
        }
    }
    
    /**
     * 
     * @param string $email the email 
     * @param object $bean the mail bean object
     */
    public function generateReport($email, $bean) {
        $report = R::dispense('report');
        $report->to = $email;
        $report->parent = $bean->id;
        R::store($report);
        
    }
    
    /**
     * /edit Edit action
     */
    public function editAction() {
      
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        
        http::prg();
        
        //include_once "templates/jquery-markedit/common.php";
        // jquery_markedit_load_assets();

        $ary = q::select($this->table)->filter('id =', $id)->fetchSingle();

        if (isset($_POST['send'])){

            //$this->validateMail();    
            if (!empty($this->errors)) {
                echo html::getErrors($this->errors);
            } else {
                
                $_POST = html::specialDecode($_POST);
                
                $values = array ();
                $values['subject'] = $_POST['subject'];
                $values['email'] = $_POST['email'];
                $values['exports'] = $_POST['exports'];


                $res = rb::updateBean($this->table, $id, $values);
                if ($res) {
                    session::setActionMessage('Mail was saved');
                    http::locationHeader("/mlist/edit/$id");
                } 
            }   
        }
        
        echo html::getHeadline('Edit mail', 'h2');
        echo $this->viewOptions($id);
        $this->formMail($ary);

    }
    
    /**
     * array holding errors
     * @var array $errors 
     */
    public $errors = array ();

    /**
     * Method for validating email
     */
    public function validateMail ($email) {
        if (!valid::validateEmailAndDomain($email)) {
            return false;
        }
        return true;
    }
}

/**
 * // add tasks
 $client = new GearmanClient();
    $client->addServer();
    
    $i= 0;
    
    $results = array();
    foreach ($rows as $row) {
        
        $client->addTaskBackground("jobs_spider_job", json_encode($row), $results, $row['id']);
        log::debug("Adding job $i with the row id = $row[id]");
        $i++;
    }
    
    $client->runTasks();
 * 
 * 
 * 
  // run
 * $worker = new GearmanWorker();
    $worker->addServer(); 

    $worker->addFunction("jobs_spider_job", function(GearmanJob $job) {
        $row = (array)json_decode($job->workload());

        jobs_spider_job($row);
    });

    while (1) {
        $worker->work();
    }
 */