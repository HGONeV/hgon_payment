<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:hgon_payment/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_order',
        'label' => 'decription',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'decription,soft_descriptor,invoice_number,article',
        'iconfile' => 'EXT:hgon_payment/Resources/Public/Icons/tx_hgonpayment_domain_model_order.gif'
    ],
    'interface' => [
        'showRecordFieldList' => 'sys_language_uid, l10n_parent, l10n_diffsource, hidden, decription, soft_descriptor, invoice_number, article',
    ],
    'types' => [
        '1' => ['showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, hidden, decription, soft_descriptor, invoice_number, article, --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, starttime, endtime'],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => [
                    [
                        'LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages',
                        -1,
                        'flags-multiple'
                    ]
                ],
                'default' => 0,
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 0,
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_hgonpayment_domain_model_order',
                'foreign_table_where' => 'AND tx_hgonpayment_domain_model_order.pid=###CURRENT_PID### AND tx_hgonpayment_domain_model_order.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        't3ver_label' => [
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.versionLabel',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'items' => [
                    '1' => [
                        '0' => 'LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:labels.enabled'
                    ]
                ],
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'behaviour' => [
                'allowLanguageSynchronization' => true
            ],
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
            ],
        ],
        'endtime' => [
            'exclude' => true,
            'behaviour' => [
                'allowLanguageSynchronization' => true
            ],
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
                'range' => [
                    'upper' => mktime(0, 0, 0, 1, 1, 2038)
                ],
            ],
        ],

        'decription' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hgon_payment/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_order.decription',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'soft_descriptor' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hgon_payment/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_order.soft_descriptor',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'invoice_number' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hgon_payment/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_order.invoice_number',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ],
        ],
        'article' => [
            'exclude' => true,
            'label' => 'LLL:EXT:hgon_payment/Resources/Private/Language/locallang_db.xlf:tx_hgonpayment_domain_model_order.article',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_hgonpayment_domain_model_article',
                'foreign_field' => 'tx_order',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 0,
                    'levelLinksPosition' => 'top',
                    'showSynchronizationLink' => 1,
                    'showPossibleLocalizationRecords' => 1,
                    'showAllLocalizationLink' => 1
                ],
            ],

        ],
    
    ],
];
