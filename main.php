<?php
function connect($config) {
    $imapConnection = imap_open(sprintf('{%s:%s/imap%s}INBOX',
            $config['mailbox_to_check']['imap_hostname'],
            $config['mailbox_to_check']['imap_port'],
            $config['mailbox_to_check']['imap_options']),
            $config['mailbox_to_check']['imap_email_address'],
            $config['mailbox_to_check']['imap_password']);
    if ($imapConnection === false) {
        throw new Exception('Unable to connect to mailbox!');
    }
    return $imapConnection;
}

function markAsRead($imapConnection, $emailNumber): void {
    $status = imap_setflag_full($imapConnection, $emailNumber, "\\Seen");
    if (!$status) {
        echo sprintf('Warning: Unable to set email %s as read.%s', $emailNumber, PHP_EOL);
    }
}

function getAttachments($structure, array &$attachments, bool $inline) {
    for ($i = 0; $i < count($structure->parts); $i++) {
        $part = $structure->parts[$i];

        $isAttachment = false;
        $entry = [
            'filename' => '',
            'name' => '',
            'attachment' => '',
            'encoding' => $part->encoding,
            'inline' => $inline,
        ];

        if ($part->ifdparameters) {
            foreach ($part->dparameters as $object) {
                if (strtolower($object->attribute) == 'filename') {
                    $isAttachment = true;
                    $entry['filename'] = $object->value;
                }
            }
        }

        if ($part->ifparameters) {
            foreach ($part->parameters as $object) {
                if (strtolower($object->attribute) == 'name') {
                    $isAttachment = true;
                    $entry['name'] = $object->value;
                }
            }
        }

        if($isAttachment) {
            $attachments[] = $entry;
        }

        if(isset($part->parts)) {
            getAttachments($part, $attachments, true);
        }
    }

    return $attachments;
}

function run(): void {
    try {
        $config = include('config.php');

        if(!file_exists($config['save_attachments_to'])) {
            mkdir($config['save_attachments_to'], 777, true);
        }

        $pathSeparator = $config['path_separator'];
		$forceReconnect = $config['force_reconnect'];
        $interval = $config['check_interval_in_seconds'];
		
		echo sprintf('Connecting to mail server...%s', PHP_EOL);
		$imapConnection = connect($config);

        echo sprintf('Setting up keys...%s', PHP_EOL);
        $fingerprint = file_get_contents($config['private_key']['file']);
        if ($fingerprint === false) {
            throw new Exception('Unable read private key!');
        }

        $passwordFile = trim($config['working_directory'], $pathSeparator) . $pathSeparator . 'password.file';
        $bytesWritten = file_put_contents($passwordFile, $config['private_key']['passphrase']);
        if($bytesWritten === false) {
            throw new Exception(sprintf("Unable to write passphrase file!"));
        }

        $command = sprintf('"%s" --pinentry-mode loopback --passphrase-file="%s" --allow-secret-key-import --import "%s"',
            trim($config['path_to_gpg_application'], $pathSeparator) . $pathSeparator . 'gpg.exe',
            $passwordFile,
            $config['private_key']['file']);
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            print_r($output);
            throw new Exception(sprintf("Unable to import private key. Exit code %s", $exitCode));
        }

        if(isset($config['sender_public_key'])) {
            $command = sprintf('"%s" --import "%s"',
            trim($config['path_to_gpg_application'], $pathSeparator) . $pathSeparator . 'gpg.exe',
            $config['sender_public_key']);
            exec($command, $output, $exitCode);
            if ($exitCode !== 0) {
                print_r($output);
                throw new Exception(sprintf("Unable to import sender public key. Exit code %s", $exitCode));
            }
        }

        $informed = false;
        echo sprintf('Fetching mails...%s', PHP_EOL);
        while (true) {
			if ($forceReconnect) {
				if ($imapConnection) {
					imap_close($imapConnection);
				}
				$imapConnection = connect($config);
			}
            $emails = imap_search($imapConnection, 'UNSEEN');
            if (!$emails) {
                if (!$informed) {
                    echo sprintf('Found no new mail. Waiting for next email...%s', PHP_EOL);
                }
                $informed = true;

                sleep($interval);
                continue;
            }
            $informed = false;
            foreach ($emails as $emailNumber) {
                $emailDetail = imap_fetch_overview($imapConnection, $emailNumber, 0);
                echo sprintf('Found email %s!%s', $emailNumber, PHP_EOL);
                if ($emailDetail === false) {
                    echo sprintf('Warning: Unable to fetch data of email %s.%s', $emailNumber, PHP_EOL);
                    continue;
                }

                if (strpos($emailDetail[0]->from, '<')) {
                    $result = preg_match("/.* <(.*)>/", $emailDetail[0]->from, $matches);
                    if($result !== 1) {
                        echo sprintf('Warning: Unable to get sender of email %s.%s', $emailNumber, PHP_EOL);
                        continue;
                    }
                    $sender = $matches[1];
                } else {
                    $sender = $emailDetail[0]->from;
                }
                if (!in_array($sender, $config['sender_email_addresses'])) {
                    echo sprintf('Marking mail %s as read, since sender "%s" is not whitelisted!%s', $emailNumber, $sender, PHP_EOL);
                    markAsRead($imapConnection, $emailNumber);
                    continue;
                }

                $structure = imap_fetchstructure($imapConnection, $emailNumber);
                if (!(isset($structure->parts) && count($structure->parts))) {
                    echo sprintf('Warning: No attachment in whitelisted email %s.%s', $emailNumber, PHP_EOL);
                    markAsRead($imapConnection, $emailNumber);
                    continue;
                }

                $attachments = [];
                getAttachments($structure, $attachments, false);
                echo sprintf('Found %s attachments.%s', count($attachments), PHP_EOL);
                foreach($attachments as $attachment) {
                    $attachment['attachment'] = imap_fetchbody($imapConnection, $emailNumber, $attachment['inline'] ? $attachment['encoding'] == 0 ? "1" : "1.2" : "2");

                    if($attachment['encoding'] == 0) {
                        $fileName = trim($config['working_directory'], $pathSeparator) . $pathSeparator . $attachment['filename'] ?? $attachment['name'];
                        $bytesWritten = file_put_contents($fileName, $attachment['attachment']);
                        if ($bytesWritten === false) {
                            echo sprintf('Error: Unable to write file "%s".%s', $fileName, PHP_EOL);
                            continue;
                        }

                        $emailFile = trim($config['working_directory'], $pathSeparator) . $pathSeparator . 'decrypted.eml';
                        $command = sprintf('"%s" --pinentry-mode loopback --passphrase-file="%s" --yes --always-trust --output "%s" %s --decrypt "%s"',
                            trim($config['path_to_gpg_application'], $pathSeparator) . $pathSeparator . 'gpg.exe',
                            $passwordFile,
                            $emailFile,
                            isset($config['sender_public_key']) ? "" : "--skip-verify",
                            $fileName);
                        exec($command, $output, $exitCode);
                        if ($exitCode !== 0) {
                            print_r($output);
                            throw new Exception(sprintf("Attachment decryption failed with exit code %s", $exitCode));
                        }

                        $emailContent = file_get_contents($emailFile);
                        $mimeEmail = mailparse_msg_create();
                        mailparse_msg_parse($mimeEmail, $emailContent);
                        if ($mimeEmail === FALSE) {
                            throw new Exception(sprintf("Unable to parse mail %s!%s", $emailNumber, PHP_EOL));
                        }

                        foreach (mailparse_msg_get_structure($mimeEmail) as $mailPart) {
                            $mailPartContent = mailparse_msg_get_part_data(mailparse_msg_get_part($mimeEmail, $mailPart));
                            if (isset($mailPartContent['headers']['content-disposition']) &&
                                strpos($mailPartContent['headers']['content-disposition'], 'attachment') !== false &&
                                strpos($mailPartContent['headers']['content-type'], 'application') !== false) {
                                $mimePdf = substr($emailContent, $mailPartContent['starting-pos-body'], $mailPartContent['ending-pos-body'] - $mailPartContent['starting-pos-body']);
                                $mimePdf = base64_decode($mimePdf);
                                $pdfFileName = trim($config['save_attachments_to'], $pathSeparator) . $pathSeparator . $mailPartContent['disposition-filename'];
                                $pdfFile = fopen($pdfFileName, "w+");
                                fwrite($pdfFile, $mimePdf);
                                fclose($pdfFile);
                                echo sprintf('Saved encrypted file %s!%s', $pdfFileName, PHP_EOL);
                            }
                        }

                        mailparse_msg_free($mimeEmail);

                        continue;
                    } elseif ($attachment['encoding'] == 3) {
                        $attachment['attachment'] = base64_decode($attachment['attachment']);
                    } elseif ($attachment['encoding'] == 4) {
                        $attachment['attachment'] = quoted_printable_decode($attachment['attachment']);
                    } elseif ($attachment['encoding'] == 5) {
                        $attachment['attachment'] = quoted_printable_decode($attachment['attachment']);
                    }

                    $fileName = trim($config['save_attachments_to'], $pathSeparator) . $pathSeparator . $attachment['filename'] ?? $attachment['name'];
                    $bytesWritten = file_put_contents($fileName, $attachment['attachment']);
                    if ($bytesWritten === false) {
                        echo sprintf('Error: Unable to write file "%s".%s', $fileName, PHP_EOL);
                        continue;
                    }
                    echo sprintf('Saved file %s!%s', $fileName, PHP_EOL);
                }
            }

            sleep($interval);
        }
    } catch(Error|Exception $error) {
        echo sprintf('Unhandled exception in script:%s', PHP_EOL);
        print_r($error);
        echo sprintf('Restarting application...%s', PHP_EOL);
        run();
    }
}

run();
