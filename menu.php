<?php

namespace modules\mlist;

use diversen\html;
use diversen\lang;

class menu {
    
    /**
     * Get all sub menus
     * @return array 
     */
    public function getMenuItems () {
        $ary = [];

        $ary[] = array(
            'title' => lang::translate('Overview', null, array('no_translate' => true)),
            'url' => '/mlist/index',
            'auth' => 'admin'
        );
        
        $ary[] = array(
            'title' => lang::translate('Create new email', null, array('no_translate' => true)),
            'url' => '/mlist/create',
            'auth' => 'admin'
        );

        $ary[] = array(
            'title' => lang::translate('Mailing lists', null, array('no_translate' => true)),
            'url' => '/mlist/lists',
            'auth' => 'admin'
        );

        return $ary;
        
    }
    
    /**
     * Get menu items as a HTML string
     * @return string $html
     */
    public function getAsLiList () {
        $menus = $this->getMenuItems();
        return  $this->getSubNav($menus);
        
    }
    
    /**
     * Generate HTML menus from menu array
     * @param type $menus
     * @return string $html li and links
     */
    public function getSubNav ($menus) {
        $str = '';
        
        foreach ($menus as $menu) {
            $extra = '';
            if ($_SERVER['REQUEST_URI'] == $menu['url']) {
                $extra = 'class="uk-active"';
            }    
            $str.=  "<li $extra>" . html::createLink($menu['url'], $menu['title']) . "</li>";
        }
        return $str;
    }
}
