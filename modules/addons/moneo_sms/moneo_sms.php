<?php
/**
 * Moneo SMS - WHMCS SMS Notification Addon Module
 *
 * @package    MoneoSms
 * @author     Moneo
 * @copyright  Moneo (https://www.moneo.com.tr)
 * @license    GPLv3 (https://www.gnu.org/licenses/gpl-3.0.txt)
 * @link       https://github.com/moneo/WHMCS-SmsModule
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function moneo_sms_config(): array
{
    return [
        "name"        => "Moneo SMS",
        "description" => "SMS notification addon for WHMCS. Supports multiple SMS gateways and automated event-driven messaging.",
        "version"     => "2.0.0",
        "author"      => "Moneo",
        "language"    => "english",
    ];
}

function moneo_sms_activate(): array
{
    try {
        if (!Capsule::schema()->hasTable('mod_moneosms_settings')) {
            Capsule::schema()->create('mod_moneosms_settings', function ($table) {
                $table->increments('id');
                $table->string('api', 40)->default('');
                $table->text('apiparams')->nullable();
                $table->integer('wantsmsfield')->nullable();
                $table->integer('gsmnumberfield')->nullable();
                $table->string('dateformat', 20)->default('%d.%m.%y');
                $table->string('version', 10)->default('2.0.0');
            });

            Capsule::table('mod_moneosms_settings')->insert([
                'api'            => '',
                'apiparams'      => '',
                'wantsmsfield'   => 0,
                'gsmnumberfield' => 0,
                'dateformat'     => '%d.%m.%y',
                'version'        => '2.0.0',
            ]);
        }

        if (!Capsule::schema()->hasTable('mod_moneosms_templates')) {
            Capsule::schema()->create('mod_moneosms_templates', function ($table) {
                $table->increments('id');
                $table->string('name', 50);
                $table->enum('type', ['client', 'admin']);
                $table->string('admingsm', 255)->default('');
                $table->string('template', 1000)->default('');
                $table->string('variables', 500)->default('');
                $table->tinyInteger('active')->default(1);
                $table->string('extra', 10)->default('');
                $table->text('description')->nullable();
            });
        }

        if (!Capsule::schema()->hasTable('mod_moneosms_messages')) {
            Capsule::schema()->create('mod_moneosms_messages', function ($table) {
                $table->increments('id');
                $table->string('sender', 40)->default('');
                $table->string('to', 20)->nullable();
                $table->text('text')->nullable();
                $table->string('msgid', 50)->nullable();
                $table->string('status', 20)->nullable();
                $table->text('errors')->nullable();
                $table->text('logs')->nullable();
                $table->integer('user')->nullable();
                $table->dateTime('datetime');
                $table->index('user');
                $table->index('datetime');
            });
        }

        require_once __DIR__ . '/smsclass.php';
        $sms = new MoneoSms();
        $sms->checkHooks();

        return [
            'status'      => 'success',
            'description' => 'Moneo SMS has been activated successfully.',
        ];
    } catch (\Exception $e) {
        return [
            'status'      => 'error',
            'description' => 'Could not activate Moneo SMS: ' . $e->getMessage(),
        ];
    }
}

function moneo_sms_deactivate(): array
{
    try {
        Capsule::schema()->dropIfExists('mod_moneosms_templates');
        Capsule::schema()->dropIfExists('mod_moneosms_settings');
        Capsule::schema()->dropIfExists('mod_moneosms_messages');

        return [
            'status'      => 'success',
            'description' => 'Moneo SMS has been deactivated successfully.',
        ];
    } catch (\Exception $e) {
        return [
            'status'      => 'error',
            'description' => 'Could not deactivate Moneo SMS: ' . $e->getMessage(),
        ];
    }
}

function moneo_sms_upgrade($vars): void
{
    $version = $vars['version'];

    try {
        // Migration from v1.x to v2.0
        if (version_compare($version, '2.0.0', '<')) {
            // Increase column sizes
            if (Capsule::schema()->hasTable('mod_moneosms_templates')) {
                Capsule::schema()->table('mod_moneosms_templates', function ($table) {
                    $table->string('template', 1000)->change();
                    $table->string('extra', 10)->change();
                });
            }

            if (Capsule::schema()->hasTable('mod_moneosms_settings')) {
                Capsule::schema()->table('mod_moneosms_settings', function ($table) {
                    $table->text('apiparams')->change();
                    $table->string('dateformat', 20)->change();
                    $table->string('version', 10)->change();
                });
            }

            if (Capsule::schema()->hasTable('mod_moneosms_messages')) {
                Capsule::schema()->table('mod_moneosms_messages', function ($table) {
                    $table->string('to', 20)->change();
                    $table->string('status', 20)->change();
                });

                // Add indexes if they don't exist
                try {
                    Capsule::schema()->table('mod_moneosms_messages', function ($table) {
                        $table->index('user');
                    });
                } catch (\Exception $e) {
                    // Index may already exist
                }

                try {
                    Capsule::schema()->table('mod_moneosms_messages', function ($table) {
                        $table->index('datetime');
                    });
                } catch (\Exception $e) {
                    // Index may already exist
                }
            }

            Capsule::table('mod_moneosms_settings')->update(['version' => '2.0.0']);
        }
    } catch (\Exception $e) {
        logActivity('Moneo SMS upgrade error: ' . $e->getMessage());
    }

    require_once __DIR__ . '/smsclass.php';
    $sms = new MoneoSms();
    $sms->checkHooks();
}

function moneo_sms_output($vars): void
{
    $modulelink = $vars['modulelink'];
    $version    = $vars['version'];
    $LANG       = $vars['_lang'];

    require_once __DIR__ . '/smsclass.php';
    $class = new MoneoSms();

    // Tab navigation
    $tabs = [
        'settings'        => $LANG['settings'],
        'templates_client' => $LANG['clientsmstemplates'],
        'templates_admin'  => $LANG['adminsmstemplates'],
        'sendbulk'        => $LANG['sendsms'],
        'messages'        => $LANG['messages'],
        'update'          => $LANG['update'],
    ];

    $allowedTabs = array_keys($tabs);
    $tab = in_array($_GET['tab'] ?? '', $allowedTabs, true) ? $_GET['tab'] : 'settings';

    echo '<ul class="nav nav-tabs" role="tablist">';
    foreach ($tabs as $tabKey => $tabLabel) {
        $active = ($tab === $tabKey) ? ' class="active"' : '';
        echo '<li' . $active . '><a href="' . $modulelink . '&tab=' . $tabKey . '">' . htmlspecialchars($tabLabel) . '</a></li>';
    }
    echo '</ul>';
    echo '<div class="tab-content" style="padding: 20px 0;">';

    // --- SETTINGS TAB ---
    if ($tab === 'settings') {
        if (!empty($_POST['params']) && isset($_POST['token'])) {
            $update = [
                'api'            => trim($_POST['api'] ?? ''),
                'apiparams'      => json_encode($_POST['params']),
                'wantsmsfield'   => (int) ($_POST['wantsmsfield'] ?? 0),
                'gsmnumberfield' => (int) ($_POST['gsmnumberfield'] ?? 0),
                'dateformat'     => trim($_POST['dateformat'] ?? '%d.%m.%y'),
            ];
            try {
                Capsule::table('mod_moneosms_settings')->limit(1)->update($update);
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error saving settings: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }

        $settings  = $class->getSettings();
        $apiparams = json_decode($settings->apiparams ?? '{}');

        // Custom fields - tickbox fields
        $tickboxFields = Capsule::table('tblcustomfields')
            ->where('fieldtype', 'tickbox')
            ->where('showorder', 'on')
            ->select('id', 'fieldname')
            ->get();

        $wantsms = '';
        foreach ($tickboxFields as $field) {
            $selected = ((int)$field->id === (int)($settings->wantsmsfield ?? 0)) ? ' selected="selected"' : '';
            $wantsms .= '<option value="' . (int)$field->id . '"' . $selected . '>' . htmlspecialchars($field->fieldname) . '</option>';
        }

        // Custom fields - text fields
        $textFields = Capsule::table('tblcustomfields')
            ->where('fieldtype', 'text')
            ->where('showorder', 'on')
            ->select('id', 'fieldname')
            ->get();

        $gsmnumber = '';
        foreach ($textFields as $field) {
            $selected = ((int)$field->id === (int)($settings->gsmnumberfield ?? 0)) ? ' selected="selected"' : '';
            $gsmnumber .= '<option value="' . (int)$field->id . '"' . $selected . '>' . htmlspecialchars($field->fieldname) . '</option>';
        }

        // SMS providers
        $senders        = $class->getSenders();
        $senderOptions  = '';
        $senderFields   = '';
        foreach ($senders as $sender) {
            if (!is_array($sender)) continue;
            $sel = (($settings->api ?? '') === $sender['value']) ? ' selected="selected"' : '';
            $senderOptions .= '<option value="' . htmlspecialchars($sender['value']) . '"' . $sel . '>' . htmlspecialchars($sender['label']) . '</option>';
            if (($settings->api ?? '') === $sender['value']) {
                foreach ($sender['fields'] as $field) {
                    $fieldVal = $apiparams->$field ?? '';
                    $senderFields .= '<tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG[$field] ?? $field) . '</td>
                        <td class="fieldarea"><input type="text" name="params[' . htmlspecialchars($field) . ']" size="40" value="' . htmlspecialchars($fieldVal) . '"></td>
                    </tr>';
                }
            }
        }

        echo '
        <script type="text/javascript">
            jQuery(document).ready(function($){
                $("#api").change(function(){ $("#settingsForm").submit(); });
            });
        </script>
        <form action="' . $modulelink . '&tab=settings" method="post" id="settingsForm">
        <input type="hidden" name="token" value="' . generate_token('plain') . '" />
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">' . htmlspecialchars($LANG['settings']) . '</h3></div>
            <div class="panel-body">
                <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['sender']) . '</td>
                        <td class="fieldarea">
                            <select name="api" id="api" class="form-control select-inline">' . $senderOptions . '</select>
                        </td>
                    </tr>
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['senderid']) . '</td>
                        <td class="fieldarea"><input type="text" name="params[senderid]" size="40" value="' . htmlspecialchars($apiparams->senderid ?? '') . '"> <span class="text-muted">e.g: MyCompany</span></td>
                    </tr>
                    ' . $senderFields . '
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['signature']) . '</td>
                        <td class="fieldarea"><input type="text" name="params[signature]" size="40" value="' . htmlspecialchars($apiparams->signature ?? '') . '"> <span class="text-muted">e.g: www.example.com</span></td>
                    </tr>
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['wantsmsfield']) . '</td>
                        <td class="fieldarea">
                            <select name="wantsmsfield" class="form-control select-inline">' . $wantsms . '</select>
                        </td>
                    </tr>
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['gsmnumberfield']) . '</td>
                        <td class="fieldarea">
                            <select name="gsmnumberfield" class="form-control select-inline">' . $gsmnumber . '</select>
                        </td>
                    </tr>
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['dateformat']) . '</td>
                        <td class="fieldarea"><input type="text" name="dateformat" size="40" value="' . htmlspecialchars($settings->dateformat ?? '%d.%m.%y') . '"> <span class="text-muted">e.g: %d.%m.%y (27.01.2024)</span></td>
                    </tr>
                </table>
            </div>
        </div>
        <p align="center"><input type="submit" value="' . htmlspecialchars($LANG['save']) . '" class="btn btn-primary" /></p>
        </form>';

    // --- TEMPLATES TAB ---
    } elseif ($tab === 'templates_client' || $tab === 'templates_admin') {
        $type = ($tab === 'templates_admin') ? 'admin' : 'client';

        if (!empty($_POST['submit']) && isset($_POST['token'])) {
            $templates = Capsule::table('mod_moneosms_templates')->where('type', $type)->get();
            foreach ($templates as $data) {
                $updateData = [
                    'template' => $_POST[$data->id . '_template'] ?? $data->template,
                    'active'   => isset($_POST[$data->id . '_active']) ? 1 : 0,
                ];
                if (isset($_POST[$data->id . '_extra'])) {
                    $updateData['extra'] = trim($_POST[$data->id . '_extra']);
                }
                if (isset($_POST[$data->id . '_admingsm'])) {
                    $updateData['admingsm'] = str_replace(' ', '', $_POST[$data->id . '_admingsm']);
                }
                Capsule::table('mod_moneosms_templates')->where('id', $data->id)->update($updateData);
            }
            echo '<div class="alert alert-success">Templates saved successfully.</div>';
        }

        $templates = Capsule::table('mod_moneosms_templates')->where('type', $type)->get();

        echo '<form action="' . $modulelink . '&tab=' . $tab . '" method="post">
        <input type="hidden" name="token" value="' . generate_token('plain') . '" />
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">' . htmlspecialchars($type === 'admin' ? $LANG['adminsmstemplates'] : $LANG['clientsmstemplates']) . '</h3></div>
            <div class="panel-body">
                <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">';

        foreach ($templates as $data) {
            $active = ($data->active == 1) ? ' checked="checked"' : '';
            $desc   = json_decode($data->description ?? '{}');
            $langKey = $LANG['lang'] ?? 'english';
            $name   = isset($desc->$langKey) ? $desc->$langKey : $data->name;

            echo '<tr>
                    <td class="fieldlabel" width="30%"><strong>' . htmlspecialchars($name) . '</strong></td>
                    <td class="fieldarea">
                        <textarea class="form-control" rows="3" name="' . (int)$data->id . '_template">' . htmlspecialchars($data->template) . '</textarea>
                    </td>
                </tr>
                <tr>
                    <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['active']) . '</td>
                    <td><input type="checkbox" value="on" name="' . (int)$data->id . '_active"' . $active . '></td>
                </tr>
                <tr>
                    <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['parameter']) . '</td>
                    <td><code>' . htmlspecialchars($data->variables) . '</code></td>
                </tr>';

            if (!empty($data->extra)) {
                echo '<tr>
                    <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['ekstra']) . '</td>
                    <td class="fieldarea"><input type="text" class="form-control input-inline" name="' . (int)$data->id . '_extra" value="' . htmlspecialchars($data->extra) . '"></td>
                </tr>';
            }

            if ($type === 'admin') {
                echo '<tr>
                    <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['admingsm']) . '</td>
                    <td class="fieldarea">
                        <input type="text" class="form-control input-inline" name="' . (int)$data->id . '_admingsm" value="' . htmlspecialchars($data->admingsm) . '">
                        <span class="text-muted">' . htmlspecialchars($LANG['admingsmornek']) . '</span>
                    </td>
                </tr>';
            }

            echo '<tr><td colspan="2"><hr></td></tr>';
        }

        echo '</table>
            </div>
        </div>
        <p align="center"><input type="submit" name="submit" value="' . htmlspecialchars($LANG['save']) . '" class="btn btn-primary" /></p>
        </form>';

    // --- MESSAGES TAB ---
    } elseif ($tab === 'messages') {
        if (!empty($_GET['deletesms'])) {
            $smsid = (int) $_GET['deletesms'];
            if ($smsid > 0) {
                try {
                    Capsule::table('mod_moneosms_messages')->where('id', $smsid)->delete();
                    echo '<div class="alert alert-success">Message deleted.</div>';
                } catch (\Exception $e) {
                    echo '<div class="alert alert-danger">Could not delete message.</div>';
                }
            }
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = ((int) ($_GET['limit'] ?? 10) <= 50) ? max(1, (int) ($_GET['limit'] ?? 10)) : 10;
        $start = ($page - 1) * $limit;
        $order = (strtoupper($_GET['order'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

        $total = Capsule::table('mod_moneosms_messages')->count();
        $totalPages = max(1, (int) ceil($total / $limit));

        $messages = Capsule::table('mod_moneosms_messages as m')
            ->leftJoin('tblclients as user', 'm.user', '=', 'user.id')
            ->select('m.*', 'user.firstname', 'user.lastname')
            ->orderBy('m.datetime', $order)
            ->offset($start)
            ->limit($limit)
            ->get();

        echo '<div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">' . htmlspecialchars($LANG['messages']) . ' (' . $total . ' total)</h3></div>
            <div class="panel-body">
            <div class="table-responsive">
            <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>' . htmlspecialchars($LANG['client']) . '</th>
                    <th>' . htmlspecialchars($LANG['gsmnumber']) . '</th>
                    <th>' . htmlspecialchars($LANG['message']) . '</th>
                    <th>' . htmlspecialchars($LANG['datetime']) . '</th>
                    <th>' . htmlspecialchars($LANG['status']) . '</th>
                    <th width="20"></th>
                </tr>
            </thead>
            <tbody>';

        foreach ($messages as $data) {
            $status = $data->status;
            if ($data->msgid && empty($data->status)) {
                try {
                    $status = $class->getReport($data->msgid);
                    if ($status) {
                        Capsule::table('mod_moneosms_messages')->where('id', $data->id)->update(['status' => $status]);
                    }
                } catch (\Exception $e) {
                    $status = 'unknown';
                }
            }

            $statusLabel = htmlspecialchars($LANG[$status] ?? $status ?? '');
            $statusClass = '';
            if ($status === 'success' || $status === 'sent') {
                $statusClass = ' class="text-success"';
            } elseif ($status === 'error') {
                $statusClass = ' class="text-danger"';
            } elseif ($status === 'pending') {
                $statusClass = ' class="text-warning"';
            }

            echo '<tr>
                <td>' . (int)$data->id . '</td>
                <td>' . ($data->user ? '<a href="clientssummary.php?userid=' . (int)$data->user . '">' . htmlspecialchars(($data->firstname ?? '') . ' ' . ($data->lastname ?? '')) . '</a>' : 'N/A') . '</td>
                <td>' . htmlspecialchars($data->to ?? '') . '</td>
                <td>' . htmlspecialchars(mb_substr($data->text ?? '', 0, 80)) . (mb_strlen($data->text ?? '') > 80 ? '...' : '') . '</td>
                <td>' . htmlspecialchars($data->datetime ?? '') . '</td>
                <td' . $statusClass . '>' . $statusLabel . '</td>
                <td><a href="' . $modulelink . '&tab=messages&deletesms=' . (int)$data->id . '" onclick="return confirm(\'Are you sure?\')" title="' . htmlspecialchars($LANG['delete']) . '"><i class="fas fa-trash-alt text-danger"></i></a></td>
            </tr>';
        }

        echo '</tbody></table></div>';

        // Pagination
        if ($totalPages > 1) {
            echo '<nav><ul class="pagination">';
            for ($p = 1; $p <= $totalPages; $p++) {
                $activeClass = ($p === $page) ? ' class="active"' : '';
                echo '<li' . $activeClass . '><a href="' . $modulelink . '&tab=messages&page=' . $p . '&limit=' . $limit . '&order=' . $order . '">' . $p . '</a></li>';
            }
            echo '</ul></nav>';
        }

        echo '</div></div>';

    // --- SEND SMS TAB ---
    } elseif ($tab === 'sendbulk') {
        $settings = $class->getSettings();

        if (!empty($_POST['client']) && isset($_POST['token'])) {
            $userinf   = explode("_", $_POST['client']);
            $userid    = (int) ($userinf[0] ?? 0);
            $gsmnumber = $userinf[1] ?? '';

            $class->setGsmnumber($gsmnumber);
            $class->setMessage($_POST['message'] ?? '');
            $class->setUserid($userid);

            $result = $class->send();
            if ($result === false) {
                echo '<div class="alert alert-danger">' . $class->getErrors() . '</div>';
            } else {
                echo '<div class="alert alert-success">' . htmlspecialchars($LANG['smssent'] ?? 'SMS Sent') . ' ' . htmlspecialchars($gsmnumber) . '</div>';
            }

            if (isset($_POST['debug']) && $_POST['debug'] === 'ON') {
                echo '<div class="panel panel-info"><div class="panel-heading">Debug Log</div><div class="panel-body">' . $class->getLogs() . '</div></div>';
            }
        }

        $clients = [];
        if ($settings && $settings->gsmnumberfield && $settings->wantsmsfield) {
            $clients = Capsule::table('tblclients as a')
                ->join('tblcustomfieldsvalues as b', 'b.relid', '=', 'a.id')
                ->join('tblcustomfieldsvalues as c', 'c.relid', '=', 'a.id')
                ->where('b.fieldid', (int) $settings->gsmnumberfield)
                ->where('c.fieldid', (int) $settings->wantsmsfield)
                ->where('c.value', 'on')
                ->select('a.id', 'a.firstname', 'a.lastname', 'b.value as gsmnumber')
                ->orderBy('a.firstname')
                ->get();
        }

        $clientOptions = '<option value="">' . htmlspecialchars($LANG['selectclient']) . '</option>';
        foreach ($clients as $client) {
            $clientOptions .= '<option value="' . (int)$client->id . '_' . htmlspecialchars($client->gsmnumber) . '">'
                . htmlspecialchars($client->firstname . ' ' . $client->lastname) . ' (#' . (int)$client->id . ')</option>';
        }

        echo '
        <script>
        jQuery(function($){
            var $select = $("#clientdrop"), options = [];
            $select.find("option").each(function(){ options.push({value:$(this).val(), text:$(this).text()}); });
            $select.data("options", options);
            $("#clientFilter").on("keyup change", function(){
                var opts = $select.empty().scrollTop(0).data("options"),
                    regex = new RegExp($.trim($(this).val()), "gi");
                $.each(opts, function(i, opt){ if(opt.text.match(regex) !== null) $select.append($("<option>").text(opt.text).val(opt.value)); });
            });
        });
        </script>
        <form action="' . $modulelink . '&tab=sendbulk" method="post">
        <input type="hidden" name="token" value="' . generate_token('plain') . '" />
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">' . htmlspecialchars($LANG['sendsms']) . '</h3></div>
            <div class="panel-body">
                <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['client']) . '</td>
                        <td class="fieldarea">
                            <input id="clientFilter" type="text" class="form-control input-inline" placeholder="Type to filter clients..." style="margin-bottom:5px"><br>
                            <select name="client" multiple id="clientdrop" class="form-control" style="min-height:150px">
                                ' . $clientOptions . '
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['message']) . '</td>
                        <td class="fieldarea">
                           <textarea class="form-control" rows="6" name="message"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="fieldlabel" width="30%">' . htmlspecialchars($LANG['debug']) . '</td>
                        <td class="fieldlabel"><input type="checkbox" name="debug" value="ON"></td>
                    </tr>
                </table>
            </div>
        </div>
        <p align="center"><input type="submit" value="' . htmlspecialchars($LANG['send']) . '" class="btn btn-primary" /></p>
        </form>';

    // --- UPDATE TAB ---
    } elseif ($tab === 'update') {
        echo '<div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title">' . htmlspecialchars($LANG['update']) . '</h3></div><div class="panel-body">';
        try {
            $ch = curl_init('https://raw.githubusercontent.com/moneo/WHMCS-SmsModule/master/version.txt');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $currentversion = trim(curl_exec($ch));
            curl_close($ch);

            if ($currentversion && version_compare($version, $currentversion, '<')) {
                echo '<div class="alert alert-warning">' . ($LANG['newversion'] ?? 'A new version is available. Please visit the <a href="https://github.com/moneo/WHMCS-SmsModule">GitHub page</a> to download.') . '</div>';
            } else {
                echo '<div class="alert alert-success">' . htmlspecialchars($LANG['uptodate'] ?? 'Your addon is up to date.') . '</div>';
            }
        } catch (\Exception $e) {
            echo '<div class="alert alert-info">Could not check for updates.</div>';
        }
        echo '</div></div>';
    }

    // Show credit balance
    try {
        $credit = $class->getBalance();
        if ($credit) {
            echo '<div class="panel panel-info"><div class="panel-body"><strong>' . htmlspecialchars($LANG['credit'] ?? 'Remaining Credit') . ':</strong> ' . htmlspecialchars($credit) . '</div></div>';
        }
    } catch (\Exception $e) {
        // Silently ignore balance errors
    }

    echo '</div>'; // close tab-content

    echo '<div class="text-center text-muted" style="margin-top:10px"><small>' . ($LANG['lisans'] ?? '') . '</small></div>';
}
