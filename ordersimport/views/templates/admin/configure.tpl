{*
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="panel">
	<div class="row moduleconfig-header">
		<div class="col-xs-2 text-right">
			<img src="{$module_dir|escape:'html':'UTF-8'}logo.png" style="width: 100%"/>
		</div>
		<div class="col-xs-10 text-left">
			<h2>{l s='Import orders' mod='ordersimport'}</h2>
			<form enctype="multipart/form-data" action="{$action_url}" method="POST" id="import_form">
				<div class="form-group">
					<label for="file">File(XLSX):*</label>
					<input type="file" name="file" id="file" required="true"/>
				</div>
				<div class="buttons">
					<a class="btn btn-default" target="_blank" href="/modules/ordersimport/example.xlsx">Download example file</a>
					<button type="submit" class="btn btn-info" id="start_import">Start import</button>
				</div>
			</form>
		</div>
	</div>

	<hr />

	<div class="moduleconfig-content" style="text-align:center;">
		
	</div>
</div>
