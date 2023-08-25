<?php

use Bitrix\Main\EventManager;

EventManager::getInstance()->addEventHandler(
    'iblock',
    'OnIBlockPropertyBuildList',
    ['MTai\IBlockProperty\EListByUser', 'GetUserTypeDescription']
);