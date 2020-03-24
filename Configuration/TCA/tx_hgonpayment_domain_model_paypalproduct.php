<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_hgonpayment_domain_model_paypalproduct', 'EXT:hgon_template/Resources/Private/Language/locallang_csh_tx_hgonpayment_domain_model_paypalproduct.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_hgonpayment_domain_model_paypalproduct');
$GLOBALS['TCA']['tx_hgonpayment_domain_model_paypalproduct'] = [
	'ctrl' => [
	    'hideTable' => true,
		'title'	=> 'LLL:EXT:hgon_payment/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_paypalproduct',
		'label' => 'description',
		'label_alt' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'dividers2tabs' => TRUE,

		'languageField' => 'sys_language_uid',
		'transOrigPointerField' => 'l10n_parent',
		'transOrigDiffSourceField' => 'l10n_diffsource',
		'delete' => 'deleted',
		'enablecolumns' => [
			'disabled' => 'hidden',
		],
		'searchFields' => 'name, description, type, category, sku, product_id',
		'iconfile' => 'EXT:hgon_payment/Resources/Public/Icons/tx_hgonpayment_domain_model_paypalproduct.gif'
    ],
	'interface' => [
		'showRecordFieldList' => 'sys_language_uid, l10n_parent, l10n_diffsource, hidden, visibility, name, description, type, category, sku, product_id',
    ],
	'types' => [
		'1' => ['showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, hidden, --palette--;;1,visibility, name, description, type, category, sku, product_id,'],
	],
	'palettes' => [
		'1' => ['showitem' => ''],
	],
	'columns' => [
	
		'sys_language_uid' => [
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
			'config' => [
				'type' => 'select',
				'renderType' => 'selectSingle',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => [
					['LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages', -1],
					['LLL:EXT:lang/locallang_general.xlf:LGL.default_value', 0]
                ],
            ],
        ],
		'l10n_parent' => [
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
			'config' => [
				'type' => 'select',
				'renderType' => 'selectSingle',
				'items' => [
					['', 0],
                ],
				'foreign_table' => 'tx_hgonpayment_domain_model_paypalproduct',
				'foreign_table_where' => 'AND tx_hgonpayment_domain_model_paypalproduct.pid=###CURRENT_PID### AND tx_hgonpayment_domain_model_paypalproduct.sys_language_uid IN (-1,0)',
            ],
        ],
		'l10n_diffsource' => [
			'config' => [
				'type' => 'passthrough',
            ],
        ],

		'hidden' => [
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
			'config' => [
				'type' => 'check',
            ],
        ],

		'name' => [
			'exclude' => 0,
			'label' => 'LLL:EXT:hgon_template/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_paypalproduct.name',
			'config' => [
				'type' => 'input',
				'size' => 30,
				'eval' => 'trim'
            ],
        ],
        'description' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:hgon_template/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_paypalproduct.description',
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'richtextConfiguration' => 'default',
                'fieldControl' => [
                    'fullScreenRichtext' => [
                        'disabled' => false,
                    ],
                ],
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim,required',
            ],
        ],
        'type' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:hgon_template/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_paypalproduct.type',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'category' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:hgon_template/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_paypalproduct.category',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'sku' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:hgon_template/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_paypalproduct.sku',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'product_id' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:hgon_template/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_paypalproduct.product_id',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
    ],
];
