<?php

return [
    'options' => [
        'printing_type' => ['T-Shirt', 'Banner', 'SAV', 'Pull-up Banner', 'Backdrop', 'Sticker', 'Poster', 'Flyer', 'Flag', 'A-Frame', 'Tent/Canopy Branding', 'Vehicle Branding', 'Other'],
        'material' => ['Flex', 'SAV / Vinyl', 'Fabric', 'Cotton', 'Polyester', 'PVC', 'Foam Board', 'Paper', 'Canvas', 'Other'],
        'print_method' => ['Digital Print', 'Screen Print', 'DTF', 'DTG', 'Sublimation', 'UV Print', 'Other'],
        'unit' => ['mm', 'cm', 'inches', 'feet'],
        'finishing' => ['Eyelets', 'Hemming', 'Stitching', 'Lamination', 'Pole Pocket', 'Cutting', 'Mounting', 'Grommets', 'None', 'Other'],
        'artwork_status' => ['Ready for Print', 'Pending', 'Requires Adaptation', 'Awaiting Client Approval'],
        'urgency' => ['Normal', 'Urgent', 'Very Urgent'],
        'approval_status' => ['Pending', 'Approved', 'Rejected', 'Revision Required'],
        'vendor_confirmation' => ['Pending', 'Confirmed', 'Unable to Fulfil'],
    ],
    'information' => [
        'request_date' => ['Request date', 'date'], 'required_date' => ['Required date', 'date'],
        'requested_by' => ['Requested by', 'text'], 'brand_client' => ['Brand / client', 'text'],
        'project_activation' => ['Project / activation', 'text'], 'delivery_location' => ['Delivery location', 'text'],
        'vendor_name' => ['Vendor name', 'text'], 'urgency' => ['Urgency', 'select'],
    ],
    'vendor' => [
        'quotation_required' => ['Quotation required', 'yesno'], 'delivery_required' => ['Delivery required', 'yesno'],
        'installation_required' => ['Installation required', 'yesno'], 'quotation_amount' => ['Quotation amount (GHS)', 'number'],
        'approval_status' => ['Approval status', 'select'], 'approved_by' => ['Approved by', 'text'],
        'vendor_confirmation' => ['Vendor confirmation', 'select'], 'delivery_date' => ['Delivery date', 'date'],
        'additional_notes' => ['Additional notes', 'textarea'],
    ],
    'item_fields' => [
        'description' => 'Item / description', 'printing_type' => 'Printing type', 'material' => 'Material',
        'print_method' => 'Print method', 'dimension' => 'Dimension', 'unit' => 'Unit', 'quantity' => 'Quantity',
        'finishing' => 'Finishing', 'artwork_status' => 'Artwork status', 'remarks' => 'Remarks',
    ],
    'checklist' => [
        'dimensions' => 'Dimensions confirmed', 'quantity' => 'Quantity confirmed',
        'specification' => 'Material / specification confirmed', 'artwork' => 'Artwork attached / approved',
        'required_date' => 'Required date confirmed', 'quotation' => 'Vendor quotation received where required',
    ],
];
