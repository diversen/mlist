<?php

namespace modules\mlist;

use diversen\conf;
use diversen\lang;
use diversen\date;
use diversen\db\q;
use diversen\db\rb;
use diversen\html;
use diversen\http;
use diversen\mailsmtp;
use diversen\moduleloader;
use diversen\pagination;
use diversen\session;
use diversen\uri\direct;
use diversen\valid;
use Michelf\MarkdownExtra;
use R;

/**
 * Simple mailing list
 */
class module {

    /**
     * Connect to existing DSN
     */
    public function __construct() {
        rb::connectExisting();
    }
    
    /**
     * Check access. Only allowed for admin users
     * @return boolean $res true if access is granted, else false
     */
    public function checkAccess () {
        if (!session::isAdmin()) {
            moduleloader::setStatus(403);
            return false;
        }
        return true;
    }
    
    /**
     * Get HTML template for email
     * @return string $html
     */
    public function getHtmlTemplate() {
        $email = file_get_contents(conf::pathModules() . '/mlist/templates/template.html');
        return $email;
    }

    /**
     * Generates txt version from markdown, and returns the txt version
     * @param int $id mail id
     * @return string $txt the text version of the mail
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
     * Form for sending an email
     */
    public function formSendMail() {

        // Draw form
        $f = new html();
        $f->formStart();
        $f->legend(lang::translate('Send mail'));
        $f->label('to', lang::translate('To'));
        $f->text('to');
        $f->submit('send', lang::translate('Send mail'));
        $f->formEnd();
        echo $f->getStr();
    }
    
    /**
     * /mlist/list/ action Send an email to a list
     * @return void
     */
    public function listAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        
        if (isset($_POST['list']) && $_POST['list'] != 0) {
            $res = $this->addMailToQueue($id, $_POST['list']);
            if ($res) {
                http::locationHeader("/mlist/list/$id", lang::translate('List has been added to queue'));
            } else {
                echo html::getErrors($this->errors);
            }
        }
        echo html::getHeadline(lang::translate('Send mail to list'), 'h2');
        echo $this->viewOptions($id);
        $this->formSendToList();
        
    }
    
    /**
     * Updates status flag on an email to indicate that it should be sent
     * @param int $mail_id the email id
     * @param int $list_id the $list id
     */
    public function addMailToQueue($mail_id, $list_id) {
        
        $row = q::select('listqueue')->
                filter('list =', $list_id)->condition('AND')->
                filter('mail =', $mail_id)->
                fetchSingle();
        
        if (!empty($row)) {
            $this->errors[] = lang::translate('Mail is already added to the queue');

            return false;
        }
        
        R::begin();
        $b = rb::getBean('listqueue');
        $b->status = 0;
        $b->list = $list_id;
        $b->mail = $mail_id;
        R::store($b);
        return R::commit();
    }
    
    /**
     * View form send email to list
     */
    public function formSendToList() {

        $rows = q::select('list')->fetch();
        $ary = array ();
        $ary[0] = 'Select mailling-list';
        foreach ($rows as $row) {
            $ary[$row['id']] = $row['title'];
        }
        // Draw form
        $f = new html();
        $f->init(array(), 'send');
        $f->formStart();
        
        $f->legend(lang::translate('Send to list'));
        
        $f->selectAry('list', $ary);
        $f->label('send', '');
        $f->submit('send', lang::translate('Send mail to mailing-list'));
        $f->formEnd();
        echo $f->getStr();
    }
    
    /**
     * View composoe email form
     * @param array $ary values to preload the form with
     */
    public function formMail ($ary = array ()) {

        $f = new html();
        $f->init($ary, 'send', true);
        $f->setAutoEncode(true);
        $f->formStart();

        $f->legend(lang::translate('Edit email'));
        
        $f->label('subject', lang::translate('Subject'));
        $f->text('subject');
        
        $f->label('exports', lang::translate('Generate exports'));
        $f->checkbox('exports');
        
        $f->label('email', lang::translate('Write in markdown'));
        $f->textarea('email', null, array ( 'class' => 'markdown', 'data-uk-htmleditor' => "{mode:'split', maxsplitsize:600, markdown:true}"));

        $f->submit('send', lang::translate('Save'));
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
            http::locationHeader($localtion, lang::translate('Mail was created'));
        } else {
            die('Could not create mail');
        }
    }
    
    /**
     * / index action Display all emails
     */
    public function indexAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $per_page = 10;
        $num_rows = q::numRows('mail')->fetch();
        $p = new pagination($num_rows, $per_page);
        
        $rows = q::select('mail')->order('created', 'DESC')->limit($p->from, $per_page)->fetch();
        $this->viewEmailsOverview($rows);
        echo $p->echoPagerHTML(); 
    }
    
    /**
     * View email rows 
     * @param array $rows the rows to display
     */
    public function viewEmailsOverview ($rows) {
        foreach ($rows as $row) {
            echo html::getHeadline($row['subject'], 'h2');
            echo $this->viewOptions($row['id']);
        }
    }
    
    /**
     * View mail options
     * @param int $id
     * @return string $html
     */
    public function viewOptions($id) {
        $str = '<ul class="uk-subnav">';
        $str.= '<li>' . html::createLink("/mlist/view/$id", lang::translate('View')) . '</li>';
        $str.= '<li>' . html::createLink("/mlist/edit/$id", lang::translate('Edit')) . '</li>';
        $str.= '<li>' . html::createLink("/mlist/delete/$id", lang::translate('Delete')) . '</li>';
        $str.= '<li>' . html::createLink("/mlist/send/$id", lang::translate('Send single email')) . '</li>';
        $str.= '<li>' . html::createLink("/mlist/list/$id", lang::translate('Send to list')) . '</li>';
        $str.= '</ul>';
        return $str; 
    }
    
    /**
     * @var string $table the mail table
     */
    public $table = 'mail';
    
    /**
     * /mlist/delete action
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
        
        echo html::getHeadline(lang::translate('Delete mail'), 'h2');
        echo $this->viewOptions($id);
        
        $f = new html();
        $f->formStart();
        $f->legend(lang::translate('Delete mail'));
        $f->submit('delete', lang::translate('Delete'));
        $f->formEnd();
        echo $f->getStr();
    }
    
    /**
     * Get HTML mail part from ID
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
     * /mlist/view View HTML mail action 
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
     * /mlist/txt get txt version of email
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
     * Action that sends a test email to a single user
     * /mlist/send Send action
     */
    public function sendAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        $bean = rb::getBean('mail', 'id', $id);
        
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
                http::locationHeader("/mlist/send/$id", lang::translate("Mail was sent"));
            } else {
                $this->errors[] = lang::translate('System could not send email');
            }
        }

        echo html::getHeadline(lang::translate('Send single email'), 'h2');
        echo $this->viewOptions($id);
        $this->formSendMail();
        $this->viewReport($id);
        
    }
    
    /**
     * A form for creating a mailing list
     */
    public function formCreateList () {
        
        $f = new html();
        $f->formStart();
        $f->legend(lang::translate('Create list'));
        $f->text('list');
        $f->submit('create', lang::translate('Create new list'));
        $f->formEnd();
        echo $f->getStr();
    }
    
    /**
     * Display all lists
     * /mlist/lists action 
     * @return void
     */
    public function listsAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        if (isset($_POST['create']) && isset($_POST['list']) && !empty($_POST['list'])) {
            $bean = R::dispense('list');
            $bean->title = $_POST['list'];
            $bean->date = date::getDateNow(array('hms' => true));
            R::store($bean);
            http::locationHeader('/mlist/lists', lang::translate('List was created'));
        }
        
        echo html::getHeadline(lang::translate('Create list'), 'h2');
        $this->formCreateList();
        $this->viewLists();

    }
    
    /**
     * Edit members connected to a list
     * /mlist/members
     * @return void
     */
    public function membersAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        $id = direct::fragment(2);
        $row = q::select('list')->filter('id =', $id)->fetchSingle();
        $row = html::specialEncode($row);
        
        echo html::getHeadline(lang::translate("Edit list") . MENU_SUB_SEPARATOR_SEC .  $row['title'], 'h2');
        echo html::createLink('/mlist/lists', lang::translate("Go back to lists"));
        $this->formListAdd($id);
        
        if (isset($_POST['add'])) {
            $this->updateMembers();
        }
    }
    
    /**
     * Update members. Based on POST
     */
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
        R::commit();
        http::locationHeader("/mlist/members/$_POST[id]", lang::translate("List was updated"));
    }
    
    /**
     * Get all list members from list id
     * @param int $list_id
     * @return array $rows members
     */
    public function getListMembers ($list_id) {
        return q::select('members')->filter('list =', $list_id)->fetch();
    }
    
    /**
     * Get all mails that have to be sent
     * @return array $rows mails
     */
    public function getMailsDueToSend () {
        $rows = q::select('listqueue')->filter('status =', 0)->fetch();
        R::begin();
        foreach($rows as $row) {
            $bean = rb::getBean('listqueue', 'id', $row['id']);
            $bean->status = 2;
            R::store($bean);
        }
        R::commit();
        return $rows;
    }
    
    /**
     * Get a mail from a mail id
     * @param type $id
     * @return type
     */
    public function getMail($id) {
        return q::select('mail')->filter('id =', $id)->fetchSingle();
    }

    /**
     * Form that displays members connected to a list
     * @param int $id list id
     */
    public function formListAdd ($id) {
        $rows = q::select('members')->filter('list =', $id)->fetch();
        $str = '';
        foreach($rows as $row) {
            $str.= $row['email'] . PHP_EOL;
        }

        $f = new html();
        $f->formStart();
        $f->legend(lang::translate('Add emails to list. New emails after a newline'));
        $f->hidden('id', $id);
        $f->textarea('members', $str, array ('cols' => '80'));
        $f->submit('add', lang::translate('Update members'));
        $f->formEnd();
        echo $f->getStr();
    }
    
    
    public function viewLists () {        
        $rows = q::select('list')->fetch();
        
        $m = new menu();
        foreach($rows as $row) {
            echo html::getHeadline("$row[title]</b> ($row[date])<br />", "h4");
            
            $ary = [];
            $ary[] = array (
                'url' => "/mlist/members/$row[id]", 
                'title' => lang::translate('Edit list'));
            $ary[] = array (
                'url' => "/mlist/deletelist/$row[id]", 
                'title' => lang::translate('Delete list'));
            
            $str = '<ul class="uk-subnav">';
            $str.= $m->getSubNav($ary);
            $str.= '</ul>';
            echo $str;
            echo "<hr />";
        }
    }
    
    /**
     * Delete list action
     * /mlist/deletelist action
     * @return type
     */
    public function deletelistAction () {
        
        if (!$this->checkAccess()) {
            return;
        }
        
        echo html::getHeadline(lang::translate('Delete list'), 'h2');
        
        $id = direct::fragment(2);
        $f = new html();
        $f->formStart();
        $f->legend(lang::translate('Delete list'));
        $f->submit('delete', lang::translate('Delete'));
        $f->formEnd();
        echo $f->getStr();
        
        if (isset($_POST['delete'])) {
            $this->deleteList();
            http::locationHeader('/mlist/lists', lang::translate('List has been deleted'));
        }
    }
    
    /**
     * Delete a list
     * @return type
     */
    private function deleteList () {
        $id = direct::fragment(2);
        q::begin();
        q::delete('list')->filter('id =', $id)->exec();
        q::delete('members')->filter('list =', $id)->exec();
        return q::commit();
    }

    
    /**
     * Displays a report of whom has been sent an email
     * @param int $id the mail id
     */
    public function viewReport($id) {
        $rows = q::select('report')->filter('parent =', $id)->fetch();
        echo html::getHeadline(lang::translate("This email has been sent to"), 'h2');
        foreach($rows as $row) {

            echo $row['to'];
            echo "<hr />";
        }
    }
    
    /**
     * Generate a report on user mail the mail id
     * @param string $email the email
     * @param object $bean the mail bean object
     */
    public function generateReport($email, $mail_id, $status) {
        if (!$status) {
            $status = 0;
        } else {
            $status = 1;
        }
        
        $report = R::dispense('report');
        $report->to = $email;
        $report->parent = $mail_id;
        $report->status = $status;
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

        $ary = q::select($this->table)->filter('id =', $id)->fetchSingle();

        if (isset($_POST['send'])){
   
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
                    session::setActionMessage(lang::translate('Mail has been saved'));
                    http::locationHeader("/mlist/edit/$id");
                } 
            }   
        }
        
        echo html::getHeadline(lang::translate('Edit mail'), 'h2');
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
