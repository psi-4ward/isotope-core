<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Isotope eCommerce Workgroup 2009-2012
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_user']['palettes']['extend']	= str_replace(';{password_legend:hide}', ',translation;{password_legend:hide}', $GLOBALS['TL_DCA']['tl_user']['palettes']['extend']);
$GLOBALS['TL_DCA']['tl_user']['palettes']['group']	= str_replace(';{password_legend:hide}', ',translation;{password_legend:hide}', $GLOBALS['TL_DCA']['tl_user']['palettes']['group']);
$GLOBALS['TL_DCA']['tl_user']['palettes']['extend']	= str_replace(';{password_legend:hide}', ',translation;{password_legend:hide}', $GLOBALS['TL_DCA']['tl_user']['palettes']['extend']);
$GLOBALS['TL_DCA']['tl_user']['palettes']['custom']	= str_replace(';{password_legend:hide}', ',translation;{password_legend:hide}', $GLOBALS['TL_DCA']['tl_user']['palettes']['custom']);
$GLOBALS['TL_DCA']['tl_user']['palettes']['admin']	= str_replace(';{password_legend:hide}', ',translation;{password_legend:hide}', $GLOBALS['TL_DCA']['tl_user']['palettes']['admin']);


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_user']['fields']['admin']['eval']['tl_class'] = 'w50';

$GLOBALS['TL_DCA']['tl_user']['fields']['translation'] = array
(
	'label'		=> &$GLOBALS['TL_LANG']['tl_user']['translation'],
	'inputType'	=> 'select',
	'options'	=> array_diff_key($this->getLanguages(), array('en'=>'English')),
	'eval'		=> array('includeBlankOption'=>true, 'tl_class'=>'w50'),
);

