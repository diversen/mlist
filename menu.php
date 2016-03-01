<?php

namespace modules\mlist;

use diversen\html;
use diversen\lang;

class menu {
    
    public function getMenuItems () {
        $ary = [];

        $ary[] = array(
            'title' => lang::translate('Overview', null, array('no_translate' => true)),
            'url' => '/mlist/index',
            'auth' => 'admin'
        );
        
        $ary[] = array(
            'title' => lang::translate('Create', null, array('no_translate' => true)),
            'url' => '/mlist/create',
            'auth' => 'admin'
        );

        $ary[] = array(
            'title' => lang::translate('Lists', null, array('no_translate' => true)),
            'url' => '/mlist/lists',
            'auth' => 'admin'
        );

        return $ary;
        
    }
    
    public function getAsLiList () {
        $menus = $this->getMenuItems();
        return  $this->getSubNav($menus);
        
    }
    
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
