<?php
require_once 'modules/admin/models/ServicePlugin.php';
/**
* @package Plugins
*/
class PluginDeletependingusers extends ServicePlugin
{
    protected $featureSet = 'accounts';
    public $hasPendingItems = true;

    function getVariables()
    {
        $variables = array(
            /*T*/'Plugin Name'/*/T*/   => array(
                'type'          => 'hidden',
                'description'   => /*T*/''/*/T*/,
                'value'         => /*T*/'Delete Pending Users'/*/T*/,
            ),
            /*T*/'Enabled'/*/T*/       => array(
                'type'          => 'yesno',
                'description'   => /*T*/'Erases pending users after the amount of days selected without being approved.'/*/T*/,
                'value'         => '0',
            ),
            /*T*/'Amount of days'/*/T*/    => array(
                'type'          => 'text',
                'description'   => /*T*/'Set the amount of days before deleting a pending user from the system'/*/T*/,
                'value'         => '30',
            ),
            /*T*/'Run schedule - Minute'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number, range, list or steps'/*/T*/,
                'value'         => '30',
                'helpid'        => '8',
            ),
            /*T*/'Run schedule - Hour'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number, range, list or steps'/*/T*/,
                'value'         => '01',
            ),
            /*T*/'Run schedule - Day'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number, range, list or steps'/*/T*/,
                'value'         => '*',
            ),
            /*T*/'Run schedule - Month'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number, range, list or steps'/*/T*/,
                'value'         => '*',
            ),
            /*T*/'Run schedule - Day of the week'/*/T*/  => array(
                'type'          => 'text',
                'description'   => /*T*/'Enter number in range 0-6 (0 is Sunday) or a 3 letter shortcut (e.g. sun)'/*/T*/,
                'value'         => '*',
            ),
        );

        return $variables;
    }

    function getUsersWithStatus($status = '')
    {
        $query = "SELECT id, UNIX_TIMESTAMP(dateactivated), status FROM users WHERE status =?";
        $result = $this->db->query($query,$status);
        return $result;
    }

    function getUsersToDelete()
    {
        $arrayUsersToDelete = array();
        $daysToDeleteUser = $this->settings->get('plugin_deletependingusers_Amount of days');
        $result = $this->getUsersWithStatus(0);
        $num_rows = $result->getNumRows();
        if($num_rows > 0){
            $tempActualDate = strtotime(date('Y-m-d'));
            while(list($id, $dateactivated, $status) = $result->fetch()){
                if($status == 0){
                    $diffdate = $tempActualDate - $dateactivated;
                    $diffdate = $diffdate/(60*60*24);
                    if($diffdate >= $daysToDeleteUser){
                        $arrayUsersToDelete[] = $id;
                    }
                }
            }
        }
        return $arrayUsersToDelete;
    }

    function execute()
    {

        $arrayUsersToDelete = $this->getUsersToDelete();
        $deletedUsers = 0;
        foreach($arrayUsersToDelete as $userid) {
            $objUser = new User($userid);
            // Get number of tickets that are not closed
            $ticketCount = $objUser->getCountOfNotClosedTickets();
            // only delete the user if they have 0 not closed tickets
            if ( $ticketCount == 0 ) {
                // do not delete with server plugin
                $objUser->delete(false);
                $deletedUsers++;
            }
        }
        return array($deletedUsers." user(s) deleted");
    }

    function pendingItems()
    {
        $usersToDelete = $this->getUsersToDelete();
        $returnArray = array();
        $returnArray['data'] = array();
        if ( count($usersToDelete) > 0 ) {
            foreach ( $usersToDelete as $userID ) {
                $user = new User($userID);
                $ticketCount = $user->getCountOfNotClosedTickets();
                if ( $ticketCount == 0 ) {
                    $tmpInfo = array();
                    $tmpInfo['customer'] = '<a href="index.php?fuse=clients&controller=userprofile&view=profilecontact&frmClientID=' . $user->getId() . '">' . $user->getFullName() . '</a>';
                    $tmpInfo['email'] = $user->getEmail();
                    $returnArray['data'][] = $tmpInfo;
                }
            }
        }
        $returnArray['totalcount'] = count($returnArray['data']);
        $returnArray['headers'] = array (
            $this->user->lang('Customer'),
            $this->user->lang('E-mail'),

        );
        return $returnArray;
    }

    function output() { }

    function dashboard()
    {
        $daysToDeleteUser = !is_null($this->settings->get('plugin_deletependingusers_Amount of days')) ? $this->settings->get('plugin_deletependingusers_Amount of days')*24*60*60 : 0;
    	$query = "SELECT COUNT(id) FROM users WHERE status = ? AND NOW() - UNIX_TIMESTAMP(dateactivated) >= $daysToDeleteUser";
        $result = $this->db->query($query,0);
    	list($numberOfUsersToDelete) = $result->fetch();
        return $this->user->lang('Pending users to be deleted on next run: %d', $numberOfUsersToDelete);
    }
}
?>
