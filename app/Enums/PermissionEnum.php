<?php

namespace App\Enums;

enum PermissionEnum: string
{
    case USERS_VIEW = 'users_view';
    case USERS_CREATE = 'users_create';
    case USERS_EDIT = 'users_edit';
    case USERS_DELETE = 'users_delete';

    case HOTELS_VIEW = 'hotels_view';
    case HOTELS_CREATE = 'hotels_create';
    case HOTELS_EDIT = 'hotels_edit';
    case HOTELS_DELETE = 'hotels_delete';

    case ROLES_VIEW = 'roles_view';
    case ROLES_MANAGE = 'roles_manage';

    case PERMISSIONS_VIEW = 'permissions_view';
    case PERMISSIONS_MANAGE = 'permissions_manage';
    
    case AMENITIES_VIEW = 'amenities_view';
    case AMENITIES_MANAGE = 'amenities_manage';

    case CATEGORIES_VIEW = 'categories_view';
    case CATEGORIES_MANAGE = 'categories_manage';

    case ROOMS_VIEW = 'rooms_view';
    case ROOMS_MANAGE = 'rooms_manage';

    case MEDIA_VIEW = 'media_view';
    case MEDIA_MANAGE = 'media_manage';

    case PLANS_VIEW = 'plans_view';
    case PLANS_MANAGE = 'plans_manage';

    case STATUSES_VIEW = 'statuses_view';
    case STATUSES_MANAGE = 'statuses_manage';

    case SERVICES_VIEW = 'services_view';
    case SERVICES_MANAGE = 'services_manage';

    case GUESTS_VIEW = 'guests_view';
    case GUESTS_MANAGE = 'guests_manage';

    case BOOKINGS_VIEW = 'bookings_view';
    case BOOKINGS_MANAGE = 'bookings_manage';
    
    // case STAFF_MANAGE = 'staff_manage';
}
