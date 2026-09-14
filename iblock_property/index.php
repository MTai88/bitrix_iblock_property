<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use MTai\IBlockProperty\EListByUser;

$APPLICATION->SetTitle('Кастомное свойство инфоблока: привязка к элементам по пользователю');

Loader::includeModule('iblock');

$iblockId = (int)\Bitrix\Iblock\IblockTable::getList([
	'select' => ['ID'],
	'filter' => ['=API_CODE' => 'official_news'],
])->fetch()['ID'];

$property = CIBlockProperty::GetList([], [
	'IBLOCK_ID' => $iblockId,
	'CODE' => 'RELATED_NEWS',
])->Fetch();

$currentUserId = (int)CurrentUser::get()->getId();
$currentUser = CUser::GetByID($currentUserId)->Fetch();
$userName = trim(($currentUser['NAME'] ?? '') . ' ' . ($currentUser['LAST_NAME'] ?? ''));
?>
<?php if (!$property): ?>
	<div class="container"><p>Свойство RELATED_NEWS не найдено — запустите scripts/seed_iblock_property_demo.php на стенде.</p></div>
<?php else: ?>
<div class="container">
	<h2>Привязка к элементам по пользователю</h2>

	<p>
		Свойство <strong>«<?= htmlspecialcharsbx($property['NAME']) ?>»</strong>
		(тип <code>E</code>, пользовательский тип <code>elist_by_user</code>) ссылается
		на инфоблок «Новости компании» и выводит в выпадающем списке только элементы,
		у которых <code>CREATED_BY</code> равен ID текущего пользователя.
	</p>

	<p>Текущий пользователь: <strong><?= htmlspecialcharsbx($userName) ?></strong> (ID <?= $currentUserId ?>)</p>

	<h3>Поле свойства как его отрисовывает тип</h3>
	<?php
	// живой рендер одного значения свойства методами кастомного типа
	echo EListByUser::GetPropertyFieldHtml($property, ['VALUE' => ''], ['VALUE' => 'demo_related_news']);
	?>

	<h3>Доступно текущему пользователю (фильтр свойства)</h3>
	<ul>
		<?php $mine = EListByUser::GetElements($iblockId, $property['USER_TYPE_SETTINGS']); ?>
		<?php foreach ($mine as $item): ?>
			<li><?= htmlspecialcharsbx($item['NAME']) ?> (ID <?= (int)$item['ID'] ?>)</li>
		<?php endforeach ?>
		<?php if (empty($mine)): ?>
			<li>— нет ваших элементов —</li>
		<?php endif ?>
	</ul>

	<h3>Все элементы инфоблока (для сравнения)</h3>
	<ul>
		<?php
		$all = CIBlockElement::GetList(
			['ID' => 'ASC'],
			['IBLOCK_ID' => $iblockId],
			false,
			false,
			['ID', 'NAME', 'CREATED_BY']
		);
		while ($row = $all->GetNext()): ?>
			<li><?= $row['NAME'] ?> (ID <?= $row['ID'] ?>, автор ID <?= $row['CREATED_BY'] ?>)</li>
		<?php endwhile ?>
	</ul>
</div>
<?php endif ?>
<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
