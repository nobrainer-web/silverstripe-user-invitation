<?php

namespace FSWebWorks\SilverStripe\UserInvitations\Admin;

/**
 * Created by PhpStorm.
 * User: tn
 * Date: 06/06/2023
 * Time: 12:58
 */

use FSWebWorks\SilverStripe\UserInvitations\Model\UserInvitation;
use SilverStripe\Admin\ModelAdmin;

class UserInvitationsAdmin extends ModelAdmin
{

    private static $managed_models = [
        UserInvitation::class
    ];

    private static $url_segment = 'userinvite-admin';

    private static $menu_title = 'User invite';

    private static $menu_icon_class = 'font-icon-torso';
}
