<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Common functions for generating lists of Routines, Triggers and Events.
 *
 * @package PhpMyAdmin
 */
declare(strict_types=1);

namespace PhpMyAdmin\Rte;

use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Response;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Utils\Routine;
use PhpMyAdmin\Template;
use PhpMyAdmin\Url;
use PhpMyAdmin\Util;

/**
 * PhpMyAdmin\Rte\RteList class
 *
 * @package PhpMyAdmin
 */
class RteList
{
    /**
     * @var Words
     */
    private $words;

    /**
     * @var Template
     */
    public $template;

    /**
     * @var DatabaseInterface
     */
    private $dbi;

    /**
     * RteList constructor.
     *
     * @param DatabaseInterface $dbi DatabaseInterface object
     */
    public function __construct(DatabaseInterface $dbi)
    {
        $this->dbi = $dbi;
        $this->words = new Words();
        $this->template = new Template();
    }

    /**
     * Creates a list of items containing the relevant
     * information and some action links.
     *
     * @param string $type  One of ['routine'|'trigger'|'event']
     * @param array  $items An array of items
     *
     * @return string HTML code of the list of items
     */
    public function get($type, array $items)
    {
        global $table;

        /**
         * Conditional classes switch the list on or off
         */
        $class1 = 'hide';
        $class2 = '';
        if (! $items) {
            $class1 = '';
            $class2 = ' hide';
        }
        /**
         * Generate output
         */
        $retval  = "<!-- LIST OF " . $this->words->get('docu') . " START -->\n";
        $retval .= '<form id="rteListForm" class="ajax" action="';
        switch ($type) {
            case 'routine':
                $retval .= 'db_routines.php';
                break;
            case 'trigger':
                if (! empty($table)) {
                    $retval .= 'tbl_triggers.php';
                } else {
                    $retval .= 'db_triggers.php';
                }
                break;
            case 'event':
                $retval .= Url::getFromRoute('/database/events');
                break;
            default:
                break;
        }
        $retval .= '">';
        $retval .= Url::getHiddenInputs($GLOBALS['db'], $GLOBALS['table']);
        $retval .= "<fieldset>\n";
        $retval .= "    <legend>\n";
        $retval .= "        " . $this->words->get('title') . "\n";
        $retval .= "        "
            . Util::showMySQLDocu($this->words->get('docu')) . "\n";
        $retval .= "    </legend>\n";
        $retval .= "    <div class='$class1' id='nothing2display'>\n";
        $retval .= "      " . $this->words->get('nothing') . "\n";
        $retval .= "    </div>\n";
        $retval .= "    <table class='data$class2'>\n";
        $retval .= "        <!-- TABLE HEADERS -->\n";
        $retval .= "        <tr>\n";
        // th cells with a colspan need corresponding td cells, according to W3C
        switch ($type) {
            case 'routine':
                $retval .= "            <th></th>\n";
                $retval .= "            <th>" . __('Name') . "</th>\n";
                $retval .= "            <th colspan='4'>" . __('Action') . "</th>\n";
                $retval .= "            <th>" . __('Type') . "</th>\n";
                $retval .= "            <th>" . __('Returns') . "</th>\n";
                $retval .= "        </tr>\n";
                $retval .= "        <tr class='hide'>\n"; // see comment above
                for ($i = 0; $i < 7; $i++) {
                    $retval .= "            <td></td>\n";
                }
                break;
            case 'trigger':
                $retval .= "            <th></th>\n";
                $retval .= "            <th>" . __('Name') . "</th>\n";
                if (empty($table)) {
                    $retval .= "            <th>" . __('Table') . "</th>\n";
                }
                $retval .= "            <th colspan='3'>" . __('Action') . "</th>\n";
                $retval .= "            <th>" . __('Time') . "</th>\n";
                $retval .= "            <th>" . __('Event') . "</th>\n";
                $retval .= "        </tr>\n";
                $retval .= "        <tr class='hide'>\n"; // see comment above
                for ($i = 0; $i < (empty($table) ? 7 : 6); $i++) {
                    $retval .= "            <td></td>\n";
                }
                break;
            case 'event':
                $retval .= "            <th></th>\n";
                $retval .= "            <th>" . __('Name') . "</th>\n";
                $retval .= "            <th>" . __('Status') . "</th>\n";
                $retval .= "            <th colspan='3'>" . __('Action') . "</th>\n";
                $retval .= "            <th>" . __('Type') . "</th>\n";
                $retval .= "        </tr>\n";
                $retval .= "        <tr class='hide'>\n"; // see comment above
                for ($i = 0; $i < 6; $i++) {
                    $retval .= "            <td></td>\n";
                }
                break;
            default:
                break;
        }
        $retval .= "        </tr>\n";
        $retval .= "        <!-- TABLE DATA -->\n";
        $response = Response::getInstance();
        foreach ($items as $item) {
            if ($response->isAjax() && empty($_REQUEST['ajax_page_request'])) {
                $rowclass = 'ajaxInsert hide';
            } else {
                $rowclass = '';
            }
            // Get each row from the correct function
            switch ($type) {
                case 'routine':
                    $retval .= $this->getRoutineRow($item, $rowclass);
                    break;
                case 'trigger':
                    $retval .= $this->getTriggerRow($item, $rowclass);
                    break;
                case 'event':
                    $retval .= $this->getEventRow($item, $rowclass);
                    break;
                default:
                    break;
            }
        }
        $retval .= "    </table>\n";

        if (count($items)) {
            $retval .= '<div class="withSelected">';
            $retval .= $this->template->render('select_all', [
                'pma_theme_image' => $GLOBALS['pmaThemeImage'],
                'text_dir' => $GLOBALS['text_dir'],
                'form_name' => 'rteListForm',
            ]);
            $retval .= Util::getButtonOrImage(
                'submit_mult',
                'mult_submit',
                __('Export'),
                'b_export',
                'export'
            );
            $retval .= Util::getButtonOrImage(
                'submit_mult',
                'mult_submit',
                __('Drop'),
                'b_drop',
                'drop'
            );
            $retval .= '</div>';
        }

        $retval .= "</fieldset>\n";
        $retval .= "</form>\n";
        $retval .= "<!-- LIST OF " . $this->words->get('docu') . " END -->\n";

        return $retval;
    }

    /**
     * Creates the contents for a row in the list of routines
     *
     * @param array  $routine  An array of routine data
     * @param string $rowclass Additional class
     *
     * @return string HTML code of a row for the list of routines
     */
    public function getRoutineRow(array $routine, $rowclass = '')
    {
        global $db, $table, $titles;

        $sql_drop = sprintf(
            'DROP %s IF EXISTS %s',
            $routine['type'],
            Util::backquote($routine['name'])
        );

        $retval  = "        <tr class='$rowclass'>\n";
        $retval .= "            <td>\n";
        $retval .= '                <input type="checkbox"'
            . ' class="checkall" name="item_name[]"'
            . ' value="' . htmlspecialchars($routine['name']) . '">';
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= "                <span class='drop_sql hide'>"
            . htmlspecialchars($sql_drop) . "</span>\n";
        $retval .= "                <strong>\n";
        $retval .= "                    "
            . htmlspecialchars($routine['name']) . "\n";
        $retval .= "                </strong>\n";
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";

        // this is for our purpose to decide whether to
        // show the edit link or not, so we need the DEFINER for the routine
        $where = "ROUTINE_SCHEMA " . Util::getCollateForIS() . "="
            . "'" . $this->dbi->escapeString($db) . "' "
            . "AND SPECIFIC_NAME='" . $this->dbi->escapeString($routine['name']) . "'"
            . "AND ROUTINE_TYPE='" . $this->dbi->escapeString($routine['type']) . "'";
        $query = "SELECT `DEFINER` FROM INFORMATION_SCHEMA.ROUTINES WHERE $where;";
        $routine_definer = $this->dbi->fetchValue($query);

        $curr_user = $this->dbi->getCurrentUser();

        // Since editing a procedure involved dropping and recreating, check also for
        // CREATE ROUTINE privilege to avoid lost procedures.
        if ((Util::currentUserHasPrivilege('CREATE ROUTINE', $db)
            && $curr_user == $routine_definer)
            || $this->dbi->isSuperuser()
        ) {
            $retval .= '                <a class="ajax edit_anchor"'
                . ' href="db_routines.php'
                . Url::getCommon([
                    'db' => $db,
                    'table' => $table,
                    'edit_item' => 1,
                    'item_name' => $routine['name'],
                    'item_type' => $routine['type'],
                ]) . '">' . $titles['Edit'] . "</a>\n";
        } else {
            $retval .= "                {$titles['NoEdit']}\n";
        }
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";

        // There is a problem with Util::currentUserHasPrivilege():
        // it does not detect all kinds of privileges, for example
        // a direct privilege on a specific routine. So, at this point,
        // we show the Execute link, hoping that the user has the correct rights.
        // Also, information_schema might be hiding the ROUTINE_DEFINITION
        // but a routine with no input parameters can be nonetheless executed.

        // Check if the routine has any input parameters. If it does,
        // we will show a dialog to get values for these parameters,
        // otherwise we can execute it directly.

        $definition = $this->dbi->getDefinition(
            $db,
            $routine['type'],
            $routine['name']
        );
        if ($definition !== null) {
            $parser = new Parser($definition);

            /**
             * @var CreateStatement $stmt
             */
            $stmt = $parser->statements[0];

            $params = Routine::getParameters($stmt);

            if (Util::currentUserHasPrivilege('EXECUTE', $db)) {
                $execute_action = 'execute_routine';
                for ($i = 0; $i < $params['num']; $i++) {
                    if ($routine['type'] == 'PROCEDURE'
                        && $params['dir'][$i] == 'OUT'
                    ) {
                        continue;
                    }
                    $execute_action = 'execute_dialog';
                    break;
                }
                $queryPart = [
                    $execute_action => 1,
                    'item_name' => $routine['name'],
                    'item_type' => $routine['type'],
                ];
                $retval .= '                <a class="ajax exec_anchor"'
                    . ' href="db_routines.php'
                    . Url::getCommon([
                        'db' => $db,
                        'table' => $table,
                    ])
                    . ($execute_action === 'execute_routine'
                        ? '" data-post="' . Url::getCommon($queryPart, '')
                        : Url::getCommon($queryPart, '&')
                    )
                    . '">' . $titles['Execute'] . "</a>\n";
            } else {
                $retval .= "                {$titles['NoExecute']}\n";
            }
        }

        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        if ((Util::currentUserHasPrivilege('CREATE ROUTINE', $db)
            && $curr_user == $routine_definer)
            || $this->dbi->isSuperuser()
        ) {
            $retval .= '                <a class="ajax export_anchor"'
                . ' href="db_routines.php'
                . Url::getCommon([
                    'db' => $db,
                    'table' => $table,
                    'export_item' => 1,
                    'item_name' => $routine['name'],
                    'item_type' => $routine['type'],
                ]) . '">' . $titles['Export'] . "</a>\n";
        } else {
            $retval .= "                {$titles['NoExport']}\n";
        }
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= Util::linkOrButton(
            Url::getFromRoute('/sql', [
                'db' => $db,
                'table' => $table,
                'sql_query' => $sql_drop,
                'goto' => 'db_routines.php?db=' . $db,
            ]),
            $titles['Drop'],
            ['class' => 'ajax drop_anchor']
        );
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= "                 {$routine['type']}\n";
        $retval .= "            </td>\n";
        $retval .= "            <td dir=\"ltr\">\n";
        $retval .= "                "
            . htmlspecialchars($routine['returns']) . "\n";
        $retval .= "            </td>\n";
        $retval .= "        </tr>\n";

        return $retval;
    }

    /**
     * Creates the contents for a row in the list of triggers
     *
     * @param array  $trigger  An array of routine data
     * @param string $rowclass Additional class
     *
     * @return string HTML code of a cell for the list of triggers
     */
    public function getTriggerRow(array $trigger, $rowclass = '')
    {
        global $db, $table, $titles;

        $retval  = "        <tr class='$rowclass'>\n";
        $retval .= "            <td>\n";
        $retval .= '                <input type="checkbox"'
            . ' class="checkall" name="item_name[]"'
            . ' value="' . htmlspecialchars($trigger['name']) . '">';
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= "                <span class='drop_sql hide'>"
            . htmlspecialchars($trigger['drop']) . "</span>\n";
        $retval .= "                <strong>\n";
        $retval .= "                    " . htmlspecialchars($trigger['name']) . "\n";
        $retval .= "                </strong>\n";
        $retval .= "            </td>\n";
        if (empty($table)) {
            $retval .= "            <td>\n";
            $retval .= '<a href="db_triggers.php'
                . Url::getCommon([
                    'db' => $db,
                    'table' => $trigger['table'],
                ]) . '">'
                . htmlspecialchars($trigger['table']) . "</a>";
            $retval .= "            </td>\n";
        }
        $retval .= "            <td>\n";
        if (Util::currentUserHasPrivilege('TRIGGER', $db, $table)) {
            $retval .= '                <a class="ajax edit_anchor"'
                . ' href="db_triggers.php'
                . Url::getCommon([
                    'db' => $db,
                    'table' => $table,
                    'edit_item' => 1,
                    'item_name' => $trigger['name'],
                ]) . '">' . $titles['Edit'] . "</a>\n";
        } else {
            $retval .= "                {$titles['NoEdit']}\n";
        }
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= '                    <a class="ajax export_anchor"'
            . ' href="db_triggers.php'
            . Url::getCommon([
                'db' => $db,
                'table' => $table,
                'export_item' => 1,
                'item_name' => $trigger['name'],
            ]) . '">' . $titles['Export'] . "</a>\n";
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        if (Util::currentUserHasPrivilege('TRIGGER', $db)) {
            $retval .= Util::linkOrButton(
                Url::getFromRoute('/sql', [
                    'db' => $db,
                    'table' => $table,
                    'sql_query' => $trigger['drop'],
                    'goto' => 'db_triggers.php?db=' . $db,
                ]),
                $titles['Drop'],
                ['class' => 'ajax drop_anchor']
            );
        } else {
            $retval .= "                {$titles['NoDrop']}\n";
        }
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= "                 {$trigger['action_timing']}\n";
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= "                 {$trigger['event_manipulation']}\n";
        $retval .= "            </td>\n";
        $retval .= "        </tr>\n";

        return $retval;
    }

    /**
     * Creates the contents for a row in the list of events
     *
     * @param array  $event    An array of routine data
     * @param string $rowclass Additional class
     *
     * @return string HTML code of a cell for the list of events
     */
    public function getEventRow(array $event, $rowclass = '')
    {
        global $db, $table, $titles;

        $sql_drop = sprintf(
            'DROP EVENT IF EXISTS %s',
            Util::backquote($event['name'])
        );

        $retval  = "        <tr class='$rowclass'>\n";
        $retval .= "            <td>\n";
        $retval .= '                <input type="checkbox"'
            . ' class="checkall" name="item_name[]"'
            . ' value="' . htmlspecialchars($event['name']) . '">';
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= "                <span class='drop_sql hide'>"
            . htmlspecialchars($sql_drop) . "</span>\n";
        $retval .= "                <strong>\n";
        $retval .= "                    "
            . htmlspecialchars($event['name']) . "\n";
        $retval .= "                </strong>\n";
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= "                 {$event['status']}\n";
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        if (Util::currentUserHasPrivilege('EVENT', $db)) {
            $retval .= '                <a class="ajax edit_anchor" href="'
                . Url::getFromRoute('/database/events', [
                    'db' => $db,
                    'table' => $table,
                    'edit_item' => 1,
                    'item_name' => $event['name'],
                ]) . '">' . $titles['Edit'] . "</a>\n";
        } else {
            $retval .= "                {$titles['NoEdit']}\n";
        }
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= '                <a class="ajax export_anchor" href="'
            . Url::getFromRoute('/database/events', [
                'db' => $db,
                'table' => $table,
                'export_item' => 1,
                'item_name' => $event['name'],
            ]) . '">' . $titles['Export'] . "</a>\n";
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        if (Util::currentUserHasPrivilege('EVENT', $db)) {
            $retval .= Util::linkOrButton(
                Url::getFromRoute('/sql', [
                    'db' => $db,
                    'table' => $table,
                    'sql_query' => $sql_drop,
                    'goto' => Url::getFromRoute('/database/events', ['db' => $db]),
                ]),
                $titles['Drop'],
                ['class' => 'ajax drop_anchor']
            );
        } else {
            $retval .= "                {$titles['NoDrop']}\n";
        }
        $retval .= "            </td>\n";
        $retval .= "            <td>\n";
        $retval .= "                 {$event['type']}\n";
        $retval .= "            </td>\n";
        $retval .= "        </tr>\n";

        return $retval;
    }
}
