<?php

namespace MTai\IBlockProperty;

use Bitrix\Iblock;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Localization\Loc;
use CAdminList;
use CIBlockElement;
use CIBlockSection;

Loc::loadMessages(__FILE__);

/**
 * Кастомный тип свойства инфоблока «Привязка к элементам по пользователю».
 *
 * Выпадающий список элементов связанного инфоблока (как у штатной
 * «Привязки к элементам»), но список отфильтрован по текущему пользователю:
 * по полю CREATED_BY связанного инфоблока или по любому другому полю,
 * указанному в настройках свойства (например PROPERTY_<код свойства>).
 *
 * Основан на \CIBlockPropertyElementList; контракт методов — стандартный
 * для обработчика события OnIBlockPropertyBuildList.
 */
class EListByUser
{
	public const USER_TYPE_ID = 'elist_by_user';

	/** Поле связанного инфоблока для фильтрации по пользователю по умолчанию */
	public const DEFAULT_USER_FIELD = 'CREATED_BY';

	public static function GetUserTypeDescription(): array
	{
		return [
			'USER_TYPE_ID' => self::USER_TYPE_ID,
			'USER_TYPE' => 'EListByUser',
			'CLASS_NAME' => __CLASS__,
			'DESCRIPTION' => Loc::getMessage('MTAI_ELIST_BY_USER_DESCRIPTION'),
			'PROPERTY_TYPE' => Iblock\PropertyTable::TYPE_ELEMENT,
			'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],
			'GetPropertyFieldHtmlMulty' => [__CLASS__, 'GetPropertyFieldHtmlMulty'],
			'GetPublicEditHTML' => [__CLASS__, 'GetPropertyFieldHtml'],
			'GetPublicEditHTMLMulty' => [__CLASS__, 'GetPropertyFieldHtmlMulty'],
			'GetPublicViewHTML' => [__CLASS__, 'GetPublicViewHTML'],
			'GetUIFilterProperty' => [__CLASS__, 'GetUIFilterProperty'],
			'GetAdminFilterHTML' => [__CLASS__, 'GetAdminFilterHTML'],
			'PrepareSettings' => [__CLASS__, 'PrepareSettings'],
			'GetSettingsHTML' => [__CLASS__, 'GetSettingsHTML'],
			'GetExtendedValue' => [__CLASS__, 'GetExtendedValue'],
			'GetUIEntityEditorProperty' => [__CLASS__, 'GetUIEntityEditorProperty'],
		];
	}

	/* Настройки свойства */

	public static function PrepareSettings($arProperty): array
	{
		$settings = is_array($arProperty['USER_TYPE_SETTINGS'] ?? null) ? $arProperty['USER_TYPE_SETTINGS'] : [];

		$size = (int)($settings['size'] ?? 0);
		$width = (int)($settings['width'] ?? 0);
		$userField = trim((string)($settings['user_field'] ?? ''));

		return [
			'size' => $size > 0 ? $size : 1,
			'width' => $width > 0 ? $width : 0,
			'group' => ($settings['group'] ?? 'N') === 'Y' ? 'Y' : 'N',
			'multiple' => ($settings['multiple'] ?? 'N') === 'Y' ? 'Y' : 'N',
			'user_field' => $userField !== '' ? $userField : self::DEFAULT_USER_FIELD,
		];
	}

	public static function GetSettingsHTML($arProperty, $strHTMLControlName, &$arPropertyFields): string
	{
		$settings = self::PrepareSettings($arProperty);
		$name = $strHTMLControlName['NAME'];

		$arPropertyFields = [
			'HIDE' => ['ROW_COUNT', 'COL_COUNT', 'MULTIPLE_CNT'],
		];

		return '
		<tr valign="top">
			<td>' . Loc::getMessage('MTAI_ELIST_BY_USER_SETTING_SIZE') . ':</td>
			<td><input type="text" size="5" name="' . $name . '[size]" value="' . $settings['size'] . '"></td>
		</tr>
		<tr valign="top">
			<td>' . Loc::getMessage('MTAI_ELIST_BY_USER_SETTING_WIDTH') . ':</td>
			<td><input type="text" size="5" name="' . $name . '[width]" value="' . $settings['width'] . '">px</td>
		</tr>
		<tr valign="top">
			<td>' . Loc::getMessage('MTAI_ELIST_BY_USER_SETTING_GROUP') . ':</td>
			<td><input type="checkbox" name="' . $name . '[group]" value="Y"' . ($settings['group'] === 'Y' ? ' checked' : '') . '></td>
		</tr>
		<tr valign="top">
			<td>' . Loc::getMessage('MTAI_ELIST_BY_USER_SETTING_MULTIPLE') . ':</td>
			<td><input type="checkbox" name="' . $name . '[multiple]" value="Y"' . ($settings['multiple'] === 'Y' ? ' checked' : '') . '></td>
		</tr>
		<tr valign="top">
			<td>' . Loc::getMessage('MTAI_ELIST_BY_USER_SETTING_USER_FIELD') . ':</td>
			<td><input type="text" name="' . $name . '[user_field]" value="' . htmlspecialcharsbx($settings['user_field']) . '"></td>
		</tr>
		';
	}

	/* Редактирование значения */

	public static function GetPropertyFieldHtml($arProperty, $value, $strHTMLControlName): string
	{
		$settings = self::PrepareSettings($arProperty);
		$wasSelected = false;
		$options = self::GetOptionsHtml($arProperty, [$value['VALUE']], $wasSelected);

		$html = '<select name="' . $strHTMLControlName['VALUE'] . '"' . self::getSelectAttributes($settings) . '>';
		if ($arProperty['IS_REQUIRED'] !== 'Y')
		{
			$html .= '<option value=""' . (!$wasSelected ? ' selected' : '') . '>'
				. Loc::getMessage('MTAI_ELIST_BY_USER_NO_VALUE') . '</option>';
		}
		$html .= $options . '</select>';

		return $html;
	}

	public static function GetPropertyFieldHtmlMulty($arProperty, $value, $strHTMLControlName): string
	{
		$maxN = 0;
		$values = [];
		if (is_array($value))
		{
			foreach ($value as $propertyValueId => $arValue)
			{
				$values[$propertyValueId] = is_array($arValue) ? $arValue['VALUE'] : $arValue;
				if (preg_match('/^n(\d+)$/', (string)$propertyValueId, $match))
				{
					$maxN = max($maxN, (int)$match[1]);
				}
			}
		}

		$settings = self::PrepareSettings($arProperty);

		if ($settings['multiple'] === 'Y')
		{
			$wasSelected = false;
			$options = self::GetOptionsHtml($arProperty, $values, $wasSelected);

			$html = '<input type="hidden" name="' . $strHTMLControlName['VALUE'] . '[]" value="">';
			$html .= '<select multiple name="' . $strHTMLControlName['VALUE'] . '[]"' . self::getSelectAttributes($settings) . '>';
			if ($arProperty['IS_REQUIRED'] !== 'Y')
			{
				$html .= '<option value=""' . (!$wasSelected ? ' selected' : '') . '>'
					. Loc::getMessage('MTAI_ELIST_BY_USER_NO_VALUE') . '</option>';
			}

			return $html . $options . '</select>';
		}

		if ((string)end($values) !== '' || mb_substr((string)key($values), 0, 1) !== 'n')
		{
			$values['n' . ($maxN + 1)] = '';
		}

		$name = $strHTMLControlName['VALUE'] . 'VALUE';
		$tableId = 'tb' . md5($name);

		$html = '<table cellpadding="0" cellspacing="0" border="0" class="nopadding" width="100%" id="' . $tableId . '">';
		foreach ($values as $propertyValueId => $value)
		{
			$wasSelected = false;
			$options = self::GetOptionsHtml($arProperty, [$value], $wasSelected);

			$html .= '<tr><td><select name="' . $strHTMLControlName['VALUE'] . '[' . $propertyValueId . '][VALUE]"'
				. self::getSelectAttributes($settings) . '>';
			$html .= '<option value=""' . (!$wasSelected ? ' selected' : '') . '>'
				. Loc::getMessage('MTAI_ELIST_BY_USER_NO_VALUE') . '</option>';
			$html .= $options . '</select></td></tr>';
		}
		$html .= '</table>';

		$html .= '<input type="button" value="' . Loc::getMessage('MTAI_ELIST_BY_USER_ADD')
			. '" onClick="BX.IBlock.Tools.addNewRow(\'' . $tableId . '\', -1)">';

		return $html;
	}

	/* Фильтры (админ-список и UI-грид) */

	public static function GetAdminFilterHTML($arProperty, $strHTMLControlName): string
	{
		$lAdmin = new CAdminList($strHTMLControlName['TABLE_ID']);
		$lAdmin->InitFilter([$strHTMLControlName['VALUE']]);
		$filterValue = $GLOBALS[$strHTMLControlName['VALUE']];

		$values = is_array($filterValue) ? $filterValue : [];

		$settings = self::PrepareSettings($arProperty);
		$wasSelected = false;
		$options = self::GetOptionsHtml($arProperty, $values, $wasSelected);

		$html = '<select multiple name="' . $strHTMLControlName['VALUE'] . '[]"' . self::getSelectAttributes($settings) . '>';
		$html .= '<option value=""' . (!$wasSelected ? ' selected' : '') . '>'
			. Loc::getMessage('MTAI_ELIST_BY_USER_ANY_VALUE') . '</option>';

		return $html . $options . '</select>';
	}

	public static function GetUIFilterProperty($arProperty, $strHTMLControlName, &$fields): void
	{
		$fields['type'] = 'list';
		$fields['items'] = self::getItemsForUiFilter($arProperty);
		$fields['operators'] = [
			'default' => '=',
			'enum' => '@',
		];
	}

	/* Публичный вывод */

	public static function GetPublicViewHTML($arProperty, $arValue, $strHTMLControlName)
	{
		static $cache = [];

		$strResult = '';
		$arValue['VALUE'] = (int)$arValue['VALUE'];
		if ($arValue['VALUE'] > 0)
		{
			$viewMode = '';
			$resultKey = '';
			switch ($strHTMLControlName['MODE'] ?? '')
			{
				case 'CSV_EXPORT':
					$viewMode = 'CSV_EXPORT';
					$resultKey = 'ID';
					break;
				case 'EXTERNAL_ID':
					$viewMode = 'EXTERNAL_ID';
					$resultKey = '~XML_ID';
					break;
				case 'SIMPLE_TEXT':
				case 'ELEMENT_TEMPLATE':
					$viewMode = $strHTMLControlName['MODE'];
					$resultKey = '~NAME';
					break;
			}

			if (!isset($cache[$arValue['VALUE']]))
			{
				$arFilter = [
					'ID' => $arValue['VALUE'],
					'CHECK_PERMISSIONS' => 'Y',
					'MIN_PERMISSION' => 'R',
				];
				$intIBlockID = (int)($arProperty['LINK_IBLOCK_ID'] ?? 0);
				if ($intIBlockID > 0)
				{
					$arFilter['IBLOCK_ID'] = $intIBlockID;
				}
				if ($viewMode === '')
				{
					$arFilter['ACTIVE'] = 'Y';
					$arFilter['ACTIVE_DATE'] = 'Y';
				}

				$rsElements = CIBlockElement::GetList(
					[],
					$arFilter,
					false,
					false,
					['ID', 'IBLOCK_ID', 'NAME', 'DETAIL_PAGE_URL']
				);
				if (isset($strHTMLControlName['DETAIL_URL']))
				{
					$rsElements->SetUrlTemplates($strHTMLControlName['DETAIL_URL']);
				}
				$cache[$arValue['VALUE']] = $rsElements->GetNext(true, true);
			}

			if (is_array($cache[$arValue['VALUE']]))
			{
				if ($viewMode !== '')
				{
					$strResult = $cache[$arValue['VALUE']][$resultKey];
				}
				else
				{
					$strResult = '<a href="' . $cache[$arValue['VALUE']]['DETAIL_PAGE_URL'] . '">'
						. $cache[$arValue['VALUE']]['NAME'] . '</a>';
				}
			}
		}

		return $strResult;
	}

	/**
	 * Данные для умного фильтра.
	 *
	 * @return false|array
	 */
	public static function GetExtendedValue($arProperty, $value): bool|array
	{
		$html = self::GetPublicViewHTML($arProperty, $value, ['MODE' => 'SIMPLE_TEXT']);
		if ($html !== '')
		{
			$text = htmlspecialcharsback($html);

			return [
				'VALUE' => $text,
				'UF_XML_ID' => $text,
			];
		}

		return false;
	}

	public static function GetUIEntityEditorProperty($settings, $value): array
	{
		$items = [];
		foreach (self::GetElements($settings['LINK_IBLOCK_ID'], $settings) as $element)
		{
			$items[] = [
				'NAME' => $element['NAME'],
				'VALUE' => $element['ID'],
				'ID' => $element['ID'],
			];
		}

		return [
			'type' => ($settings['MULTIPLE'] === 'Y') ? 'multilist' : 'list',
			'data' => [
				'isProductProperty' => true,
				'enableEmptyItem' => true,
				'items' => $items,
				'isConfigurable' => false,
			],
		];
	}

	/* Внутренние хелперы */

	/**
	 * Опции <option> для селекта: элементы связанного инфоблока, отфильтрованные
	 * по текущему пользователю; при group=Y — сгруппированные в <optgroup> по разделам.
	 */
	public static function GetOptionsHtml($arProperty, $values, &$bWasSelect): string
	{
		$values = array_map('strval', is_array($values) ? $values : []);
		$bWasSelect = false;
		$settings = self::PrepareSettings($arProperty);

		$renderOption = function (array $item) use ($values, &$bWasSelect): string {
			$selected = in_array((string)$item['~ID'], $values, true);
			$bWasSelect = $bWasSelect || $selected;

			return '<option value="' . $item['ID'] . '"' . ($selected ? ' selected' : '') . '>'
				. $item['NAME'] . '</option>';
		};

		$options = '';
		if ($settings['group'] === 'Y')
		{
			$arElements = self::GetElements($arProperty['LINK_IBLOCK_ID'], $settings);
			$arTree = self::GetSections($arProperty['LINK_IBLOCK_ID']);
			foreach ($arElements as $i => $arElement)
			{
				if (
					$arElement['IN_SECTIONS'] === 'Y'
					&& array_key_exists($arElement['IBLOCK_SECTION_ID'], $arTree)
				)
				{
					$arTree[$arElement['IBLOCK_SECTION_ID']]['E'][] = $arElement;
					unset($arElements[$i]);
				}
			}

			foreach ($arTree as $arSection)
			{
				$options .= '<optgroup label="' . str_repeat(' . ', $arSection['DEPTH_LEVEL'] - 1) . $arSection['NAME'] . '">';
				if (isset($arSection['E']))
				{
					foreach ($arSection['E'] as $arItem)
					{
						$options .= $renderOption($arItem);
					}
				}
				$options .= '</optgroup>';
			}
		}

		foreach (self::GetElements($arProperty['LINK_IBLOCK_ID'], $settings) as $arItem)
		{
			if (!($settings['group'] === 'Y' && $arItem['IN_SECTIONS'] === 'Y'))
			{
				$options .= $renderOption($arItem);
			}
		}

		return $options;
	}

	private static function getItemsForUiFilter($arProperty): array
	{
		$items = [];
		foreach (self::GetElements($arProperty['LINK_IBLOCK_ID'], self::PrepareSettings($arProperty)) as $arItem)
		{
			$items[$arItem['ID']] = $arItem['NAME'];
		}

		return $items;
	}

	private static function getSelectAttributes(array $settings): string
	{
		$attributes = '';
		if ((int)$settings['size'] > 1)
		{
			$attributes .= ' size="' . (int)$settings['size'] . '"';
		}
		if ((int)$settings['width'] > 0)
		{
			$attributes .= ' style="width:' . (int)$settings['width'] . 'px"';
		}

		return $attributes;
	}

	/**
	 * Элементы связанного инфоблока, доступные текущему пользователю.
	 * Фильтр: поле $settings['user_field'] (по умолчанию CREATED_BY) = ID текущего пользователя.
	 */
	public static function GetElements($IBLOCK_ID, $settings)
	{
		static $cache = [];
		$IBLOCK_ID = (int)$IBLOCK_ID;

		if (!array_key_exists($IBLOCK_ID, $cache))
		{
			$cache[$IBLOCK_ID] = [];
			if ($IBLOCK_ID > 0)
			{
				$userField = trim((string)($settings['user_field'] ?? ''));
				if ($userField === '')
				{
					$userField = self::DEFAULT_USER_FIELD;
				}

				$rsItems = CIBlockElement::GetList(
					['NAME' => 'ASC', 'ID' => 'ASC'],
					[
						'IBLOCK_ID' => $IBLOCK_ID,
						'CHECK_PERMISSIONS' => 'Y',
						$userField => CurrentUser::get()->getId(),
					],
					false,
					false,
					['ID', 'NAME', 'IN_SECTIONS', 'IBLOCK_SECTION_ID']
				);
				while ($arItem = $rsItems->GetNext())
				{
					$cache[$IBLOCK_ID][] = $arItem;
				}
			}
		}

		return $cache[$IBLOCK_ID];
	}

	public static function GetSections($IBLOCK_ID)
	{
		static $cache = [];
		$IBLOCK_ID = (int)$IBLOCK_ID;

		if (!array_key_exists($IBLOCK_ID, $cache))
		{
			$cache[$IBLOCK_ID] = [];
			if ($IBLOCK_ID > 0)
			{
				$rsItems = CIBlockSection::GetList(
					['LEFT_MARGIN' => 'ASC'],
					[
						'IBLOCK_ID' => $IBLOCK_ID,
						'CHECK_PERMISSIONS' => 'Y',
					],
					false,
					['ID', 'NAME', 'DEPTH_LEVEL']
				);
				while ($arItem = $rsItems->GetNext())
				{
					$cache[$IBLOCK_ID][$arItem['ID']] = $arItem;
				}
			}
		}

		return $cache[$IBLOCK_ID];
	}
}
