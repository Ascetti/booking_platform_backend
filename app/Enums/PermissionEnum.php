<?php

namespace App\Enums;

enum PermissionEnum: string
{
    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_EDIT = 'users.edit';
    case USERS_DELETE = 'users.delete';

    case HOTELS_VIEW = 'hotels.view';
    case HOTELS_CREATE = 'hotels.create';
    case HOTELS_EDIT = 'hotels.edit';
    case HOTELS_DELETE = 'hotels.delete';

    case ROLES_MANAGE = 'roles.manage';
    case PERMISSIONS_MANAGE = 'permissions.manage';

    // case STAFF_MANAGE = 'staff.manage';
}
