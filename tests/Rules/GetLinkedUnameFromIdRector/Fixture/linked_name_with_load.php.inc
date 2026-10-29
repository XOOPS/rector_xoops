<?php

function poster($uid)
{
    $name = xoops_getLinkedUnameFromId($uid);
    echo xoops_getLinkedUnameFromId($uid);
    if (xoops_getLinkedUnameFromId($uid) === '') {
        return '';
    }
    $n = class_exists('XoopsUserUtility', false) ? xoops_getLinkedUnameFromId($uid) : 'missing';
    $named = xoops_getLinkedUnameFromId(userid: $uid);
    $spread = xoops_getLinkedUnameFromId(...$ids);
    echo class_exists('XoopsUserUtility', false) ? 'loaded' : 'absent', xoops_getLinkedUnameFromId($uid);
    echo xoops_getLinkedUnameFromId(class_exists('XoopsUserUtility', false) ? 1 : 2);
    $cb = function ($id) {
        return xoops_getLinkedUnameFromId($id);
    };

    return xoops_getLinkedUnameFromId($uid);
}

?>
-----
<?php

function poster($uid)
{
    \xoops_load('XoopsUserUtility');
    $name = \XoopsUserUtility::getUnameFromId($uid, false, true);
    \xoops_load('XoopsUserUtility');
    echo \XoopsUserUtility::getUnameFromId($uid, false, true);
    if (xoops_getLinkedUnameFromId($uid) === '') {
        return '';
    }
    $n = class_exists('XoopsUserUtility', false) ? xoops_getLinkedUnameFromId($uid) : 'missing';
    $named = xoops_getLinkedUnameFromId(userid: $uid);
    $spread = xoops_getLinkedUnameFromId(...$ids);
    echo class_exists('XoopsUserUtility', false) ? 'loaded' : 'absent', xoops_getLinkedUnameFromId($uid);
    echo xoops_getLinkedUnameFromId(class_exists('XoopsUserUtility', false) ? 1 : 2);
    $cb = function ($id) {
        \xoops_load('XoopsUserUtility');
        return \XoopsUserUtility::getUnameFromId($id, false, true);
    };
    \xoops_load('XoopsUserUtility');

    return \XoopsUserUtility::getUnameFromId($uid, false, true);
}

?>
