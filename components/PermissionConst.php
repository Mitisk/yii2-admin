<?php

namespace Mitisk\Yii2Admin\components;

interface PermissionConst
{
    const
        ItemView   = 'ItemView',
        ItemUpdate = 'ItemUpdate',
        ItemCreate = 'ItemCreate',
        ItemDelete = 'ItemDelete',

        UpdateOwn  = 'UpdateOwn',
        DeleteOwn  = 'DeleteOwn',
        AuthorRule  = 'AuthorRule';
}