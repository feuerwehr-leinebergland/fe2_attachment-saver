<?php
return [
    'check_interval_in_seconds' => 1,
    'mailbox_to_check' => [
        'imap_hostname' => 'example.com',
        'imap_email_address' => 'user@example.com',
        'imap_password' => 'password',
        'imap_port' => 993,
        'imap_options' => '/ssl'
    ],
    'private_key' => [
        'file' => 'C:\\path\\to\\private.key',
        'passphrase' => 'password'
    ],
    'sender_email_addresses' => [
      'mail@example.com'
    ],
    'save_attachments_to' => 'C:\\path\\to\\attachments',
    'working_directory' => 'C:\\path\\to\\working-directory',
    'path_to_gpg_application' => 'C:\\Program Files (x86)\\GnuPG\\bin\\',
    'path_separator' => '\\'
];
