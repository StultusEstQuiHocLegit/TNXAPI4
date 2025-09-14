<?php
require_once('../config.php');




$adminId = $_SESSION['IdpkOfAdmin'] ?? 0;

// 1. Fetch all tables for this admin
$stmtTables = $pdo->prepare("
    SELECT idpk, name, FurtherInformation
    FROM tables
    WHERE IdpkOfAdmin = ?
    ORDER BY idpk
");
$stmtTables->execute([$adminId]);
$tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

$DatabaseTablesStructure = [];
$DatabaseTablesFurtherInformation = [];

foreach ($tables as $table) {
    $tableId = $table['idpk'];
    $tableName = $table['name'];
    $DatabaseTablesFurtherInformation[$tableName] = trim($table['FurtherInformation'] ?? '');

    // 2. Fetch all columns for this table
    $stmtCols = $pdo->prepare("
        SELECT name, type, label, SystemOnly, DBType, DBMarks, LinkedToWhatTable, LinkedToWhatFieldThere, IsLinkedTableGeneralTable,
               placeholder, step, maxlength, HumanPrimary, OnlyShowIfThisIsTrue, price, ShowInPreviewCard, ShowToPublic, ShowWholeEntryToPublicMarker
        FROM columns
        WHERE IdpkOfTable = ?
        ORDER BY idpk
    ");
    $stmtCols->execute([$tableId]);
    $columns = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

    $columnsStructure = [];

    foreach ($columns as $col) {
        $colEntry = [];

        // Map fields exactly as in your original structure
        $colEntry['type'] = $col['type'];
        $colEntry['label'] = $col['label'];

        // Convert SystemOnly from int to string '1' or not set (to match your original)
        if ((int)$col['SystemOnly'] === 1) {
            $colEntry['SystemOnly'] = '1';
        }

        // DBType is always present
        if ($col['DBType'] !== null) {
            $colEntry['DBType'] = $col['DBType'];
        }

        // DBMarks if not null
        if ($col['DBMarks'] !== null && $col['DBMarks'] !== '') {
            $colEntry['DBMarks'] = $col['DBMarks'];
        }

        // LinkedToWhatTable: if present (integer), convert to corresponding table name
        if ($col['LinkedToWhatTable'] !== null) {
            // Query table name by idpk once per table, or cache for efficiency
            $stmtTableName = $pdo->prepare("SELECT name FROM tables WHERE idpk = ?");
            $stmtTableName->execute([$col['LinkedToWhatTable']]);
            $linkedTableName = $stmtTableName->fetchColumn();
            if ($linkedTableName !== false) {
                $colEntry['LinkedToWhatTable'] = $linkedTableName;
            }
        }

        // LinkedToWhatFieldThere if present and not empty
        if ($col['LinkedToWhatFieldThere'] !== null && $col['LinkedToWhatFieldThere'] !== '') {
            $colEntry['LinkedToWhatFieldThere'] = $col['LinkedToWhatFieldThere'];
        }

        // IsLinkedTableGeneralTable convert int to string '1' or '0' only if 1, otherwise omit
        if ((int)$col['IsLinkedTableGeneralTable'] === 1) {
            $colEntry['IsLinkedTableGeneralTable'] = '1';
        }

        // placeholder if present and not empty
        if ($col['placeholder'] !== null && $col['placeholder'] !== '') {
            $colEntry['placeholder'] = $col['placeholder'];
        }

        // step if present and not empty
        if ($col['step'] !== null && $col['step'] !== '') {
            $colEntry['step'] = $col['step'];
        }

        // maxlength if present (int), convert to string
        if ($col['maxlength'] !== null) {
            $colEntry['maxlength'] = (string)$col['maxlength'];
        }

        // HumanPrimary convert int to string '1' or omit if 0
        if ((int)$col['HumanPrimary'] === 1) {
            $colEntry['HumanPrimary'] = '1';
        }

        // OnlyShowIfThisIsTrue references a column idpk; convert to corresponding column name
        if ($col['OnlyShowIfThisIsTrue'] !== null) {
            $refColId = (int)$col['OnlyShowIfThisIsTrue'];
            if ($refColId > 0) {
                $stmtRefCol = $pdo->prepare("SELECT name FROM columns WHERE idpk = ?");
                $stmtRefCol->execute([$refColId]);
                $refColName = $stmtRefCol->fetchColumn();
                if ($refColName !== false) {
                    $colEntry['OnlyShowIfThisIsTrue'] = $refColName;
                }
            }
        }

        // Convert price from int to string '1' or not set (to match your original)
        if ((int)$col['price'] === 1) {
            $colEntry['price'] = '1';
        }

        // Convert ShowInPreviewCard from int to string '1' or not set (to match your original)
        if ((int)$col['ShowInPreviewCard'] === 1) {
            $colEntry['ShowInPreviewCard'] = '1';
        }

        // Convert ShowToPublic from int to string '1' or not set (to match your original)
        if ((int)$col['ShowToPublic'] === 1) {
            $colEntry['ShowToPublic'] = '1';
        }

        // Convert ShowWholeEntryToPublicMarker from int to string '1' or not set
        if ((int)$col['ShowWholeEntryToPublicMarker'] === 1) {
            $colEntry['ShowWholeEntryToPublicMarker'] = '1';
        }

        $columnsStructure[$col['name']] = $colEntry;
    }

    $DatabaseTablesStructure[$tableName] = $columnsStructure;
}






































// $DatabaseTablesStructure = [
//     'TDBcarts' => [
//         'idpk' => [
//             'type' => 'number',
//             'label' => 'idpk',
//             'SystemOnly' => '1',
//             'DBType' => 'int',
//             'DBMarks' => 'auto increment, primary key'
//         ],
//         'TimestampCreation' => [
//             'type' => 'datetime-local',
//             'label' => 'timestamp creation',
//             'SystemOnly' => '1',
//             'DBType' => 'timestamp',
//             'ShowInPreviewCard' => '1'
//         ],
//         'IdpkOfSupplierOrCustomer' => [
//             'type' => 'number',
//             'label' => 'supplier or customer idpk',
//             'LinkedToWhatTable' => 'TDBSuppliersAndCustomers',
//             'LinkedToWhatFieldThere' => 'idpk',
//             'IsLinkedTableGeneralTable' => '1',
//             'DBType' => 'int',
//             'ShowInPreviewCard' => '1'
//         ],
//         'DeliveryType' => [
//             'type' => 'text',
//             'label' => 'delivery type',
//             'placeholder' => 'for example: standard, express, overnight, pickup, temperature-controlled, ...',
//             'DBType' => 'varchar(250)'
//         ],
//         'WishedIdealDeliveryOrPickUpTime' => [
//             'type' => 'datetime-local',
//             'label' => 'wished delivery/pickup time',
//             'DBType' => 'datetime'
//         ],
//         'CommentsNotesSpecialRequests' => [
//             'type' => 'textarea',
//             'label' => 'comments, notes, special requests, ...',
//             'DBType' => 'text'
//         ]
//     ],
//     'TDBtransaction' => [
//         'idpk' => [
//             'type' => 'number',
//             'label' => 'idpk',
//             'SystemOnly' => '1',
//             'DBType' => 'int',
//             'DBMarks' => 'auto increment, primary key'
//         ],
//         'TimestampCreation' => [
//             'type' => 'datetime-local',
//             'label' => 'timestamp creation',
//             'SystemOnly' => '1',
//             'DBType' => 'timestamp',
//             'ShowInPreviewCard' => '1'
//         ],
//         'IdpkOfSupplierOrCustomer' => [
//             'type' => 'number',
//             'label' => 'supplier or customer idpk',
//             'LinkedToWhatTable' => 'TDBSuppliersAndCustomers',
//             'LinkedToWhatFieldThere' => 'idpk',
//             'IsLinkedTableGeneralTable' => '1',
//             'DBType' => 'int',
//             'ShowInPreviewCard' => '1'
//         ],
//         'IdpkOfCart' => [
//             'type' => 'number',
//             'label' => 'cart id',
//             'LinkedToWhatTable' => 'TDBcarts',
//             'LinkedToWhatFieldThere' => 'idpk',
//             'DBType' => 'int',
//             'ShowInPreviewCard' => '1'
//         ],
//         'IdpkOfProductOrService' => [
//             'type' => 'number',
//             'label' => 'product or service idpk',
//             'LinkedToWhatTable' => 'TDBProductsAndServices',
//             'LinkedToWhatFieldThere' => 'idpk',
//             'IsLinkedTableGeneralTable' => '1',
//             'DBType' => 'int',
//             'ShowInPreviewCard' => '1'
//         ],
//         'quantity' => [
//             'type' => 'number',
//             'label' => 'quantity',
//             'DBType' => 'int',
//             'ShowInPreviewCard' => '1'
//         ],
//         'NetPriceTotal' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'net price total',
//             'placeholder' => 'positive if you sold something, negative for buying, total = unit price * quantity',
//             'DBType' => 'decimal(10,2)',
//             'price' => '1',
//             'ShowInPreviewCard' => '1'
//         ],
//         'TaxesTotal' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'taxes total',
//             'placeholder' => 'total = unit tax amount * quantity',
//             'DBType' => 'decimal(10,2)',
//             'price' => '1',
//             'ShowInPreviewCard' => '1'
//         ],
//         'CurrencyCode' => [
//             'type' => 'text',
//             'maxlength' => '3',
//             'label' => 'currency code (ISO 4217)',
//             'DBType' => 'varchar(3)'
//         ],
//         'state' => [
//             'type' => 'text',
//             'label' => 'state',
//             'placeholder' => 'for example: draft, offer, payment-pending, payment-made-delivery-pending, payment-pending-delivery-made, completed, disputed, dispute-accepted, dispute-rejected, refunded, items-returned, cancelled, ...',
//             'DBType' => 'varchar(250)',
//             'ShowInPreviewCard' => '1'
//         ],
//         'CommentsNotesSpecialRequests' => [
//             'type' => 'textarea',
//             'label' => 'comments, notes, special requests, ...',
//             'DBType' => 'text'
//         ]
//     ],
//     'TDBProductsAndServices' => [
//         'idpk' => [
//             'type' => 'number',
//             'label' => 'idpk',
//             'SystemOnly' => '1',
//             'DBType' => 'int',
//             'DBMarks' => 'auto increment, primary key',
//             'ShowToPublic' => '1'
//         ],
//         'TimestampCreation' => [
//             'type' => 'datetime-local',
//             'label' => 'timestamp creation',
//             'SystemOnly' => '1',
//             'DBType' => 'timestamp'
//         ],
//         'name' => [
//             'type' => 'text',
//             'label' => 'name',
//             'HumanPrimary' => '1',
//             'DBType' => 'varchar(250)',
//             'ShowToPublic' => '1'
//         ],
//         'categories' => [
//             'type' => 'text',
//             'label' => 'categories',
//             'placeholder' => 'for example: SomeMainCategory/FirstSubcategory/SecondSubcategory, AnotherMainCategoryIfNeeded, YetAnotherOne/AndSomeSubcategoryToo',
//             'DBType' => 'varchar(250)',
//             'ShowToPublic' => '1'
//         ],
//         'KeywordsForSearch' => [
//             'type' => 'textarea',
//             'label' => 'keywords for search',
//             'DBType' => 'text',
//             'ShowToPublic' => '1'
//         ],
//         'ShortDescription' => [
//             'type' => 'textarea',
//             'label' => 'short description',
//             'DBType' => 'text',
//             'ShowInPreviewCard' => '1',
//             'ShowToPublic' => '1'
//         ],
//         'LongDescription' => [
//             'type' => 'textarea',
//             'label' => 'long description',
//             'DBType' => 'text',
//             'ShowToPublic' => '1'
//         ],
//         'WeightInKg' => [
//             'type' => 'number',
//             'step' => '0.00001',
//             'label' => 'weight (kg)',
//             'DBType' => 'decimal(10,5)',
//             'ShowToPublic' => '1'
//         ],
//         'DimensionsLengthInMm' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'length (mm)',
//             'DBType' => 'decimal(10,2)',
//             'ShowToPublic' => '1'
//         ],
//         'DimensionsWidthInMm' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'width (mm)',
//             'DBType' => 'decimal(10,2)',
//             'ShowToPublic' => '1'
//         ],
//         'DimensionsHeightInMm' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'height (mm)',
//             'DBType' => 'decimal(10,2)',
//             'ShowToPublic' => '1'
//         ],
//         'NetPriceInCurrencyOfAdmin' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'net price',
//             'DBType' => 'decimal(10,2)',
//             'price' => '1',
//             'ShowInPreviewCard' => '1',
//             'ShowToPublic' => '1'
//         ],
//         'TaxesInPercent' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'taxes (%)',
//             'DBType' => 'decimal(10,2)',
//             'ShowToPublic' => '1'
//         ],
//         'VariableCostsOrPurchasingPriceInCurrencyOfAdmin' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'variable costs/purchasing price',
//             'DBType' => 'decimal(10,2)'
//         ],
//         'ProductionCoefficientInLaborHours' => [
//             'type' => 'number',
//             'step' => '0.01',
//             'label' => 'production coefficient (hours)',
//             'DBType' => 'decimal(10,2)'
//         ],
//         'ManageInventory' => [
//             'type' => 'checkbox',
//             'label' => 'manage inventory',
//             'DBType' => 'boolean',
//             'ShowToPublic' => '1'
//         ],
//         'InventoryAvailable' => [
//             'type' => 'number',
//             'label' => 'available inventory',
//             'OnlyShowIfThisIsTrue' => 'ManageInventory',
//             'DBType' => 'int',
//             'ShowToPublic' => '1'
//         ],
//         'InventoryInProductionOrReordered' => [
//             'type' => 'number',
//             'label' => 'inventory in production/reordered',
//             'OnlyShowIfThisIsTrue' => 'ManageInventory',
//             'DBType' => 'int'
//         ],
//         'InventoryMinimumLevel' => [
//             'type' => 'number',
//             'label' => 'minimum inventory level',
//             'OnlyShowIfThisIsTrue' => 'ManageInventory',
//             'DBType' => 'int'
//         ],
//         'InventoryLocation' => [
//             'type' => 'text',
//             'label' => 'inventory location',
//             'DBType' => 'varchar(250)'
//         ],
//         'PersonalNotes' => [
//             'type' => 'textarea',
//             'label' => 'personal notes',
//             'DBType' => 'text'
//         ],
//         'state' => [
//             'type' => 'checkbox',
//             'label' => 'state',
//             'placeholder' => false to hide it in online shop, true to show it',
//             'DBType' => 'boolean'
//         ]
//     ],
//     'TDBSuppliersAndCustomers' => [
//         'idpk' => [
//             'type' => 'number',
//             'label' => 'idpk',
//             'SystemOnly' => '1',
//             'DBType' => 'int',
//             'DBMarks' => 'auto increment, primary key'
//         ],
//         'TimestampCreation' => [
//             'type' => 'datetime-local',
//             'label' => 'timestamp creation',
//             'SystemOnly' => '1',
//             'DBType' => 'timestamp'
//         ],
//         'CompanyName' => [
//             'type' => 'text',
//             'label' => 'company name',
//             'HumanPrimary' => '1',
//             'DBType' => 'varchar(250)'
//         ],
//         'email' => [
//             'type' => 'email',
//             'label' => 'email',
//             'DBType' => 'varchar(250)'
//         ],
//         'PhoneNumber' => [
//             'type' => 'number',
//             'label' => 'phone number',
//             'placeholder' => 'for example: 0123456789',
//             'DBType' => 'bigint'
//         ],
//         'street' => [
//             'type' => 'text',
//             'label' => 'street',
//             'DBType' => 'varchar(250)'
//         ],
//         'HouseNumber' => [
//             'type' => 'number',
//             'label' => 'house number',
//             'DBType' => 'int'
//         ],
//         'ZIPCode' => [
//             'type' => 'text',
//             'label' => 'zip code',
//             'DBType' => 'varchar(250)'
//         ],
//         'city' => [
//             'type' => 'text',
//             'label' => 'city',
//             'DBType' => 'varchar(250)'
//         ],
//         'country' => [
//             'type' => 'text',
//             'label' => 'country',
//             'DBType' => 'varchar(250)'
//         ],
//         'IBAN' => [
//             'type' => 'text',
//             'label' => 'IBAN',
//             'DBType' => 'varchar(250)'
//         ],
//         'VATID' => [
//             'type' => 'text',
//             'label' => 'VATID',
//             'DBType' => 'varchar(250)'
//         ],
//         'PersonalNotesInGeneral' => [
//             'type' => 'textarea',
//             'label' => 'general notes',
//             'DBType' => 'text'
//         ],
//         'PersonalNotesBusinessRelationships' => [
//             'type' => 'textarea',
//             'label' => 'business relationship notes',
//             'DBType' => 'text'
//         ]
//     ]
// ];













?>
