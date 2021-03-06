<?php

/**
 * @author Dirk Elmendorf
 * @style Compliant
 * @testframework Compliant
 * @package Postie 
 * @copyright Copyright 2005 Dirk Elmendorf
 */

/**
 * This class handles the details of an IMAP connection
 *
 * @author Dirk Elmendorf
 * @package Postie
 */
class PostieIMAP {

    var $_connected;
    var $_protocol;
    var $_ssl;
    var $_self_cert;
    var $_tls_on;
    var $_connection;
    var $_server_string;

    function PostieIMAP($protocol = "imap", $ssl_on = false, $self_cert = true) {
        $this->_connected = false;
        $this->_tls_on = false;
        $this->_protocol = strtolower($protocol);
        $this->_ssl = $ssl_on;
        $this->_self_cert = $self_cert;
    }

    /**
     * call this to turn on TLS
     */
    function TLSOn() {
        $this->_tls_on = true;
    }

    /**
     * call this if you want to verify the cert
     */
    function RealCert() {
        $this->self_cert = false;
    }

    /**
     * Shows if the object is actually connected
     * @return boolean
     */
    function isConnected() {
        return($this->_connected);
    }

    /**
     * Opens a connection to the server
     * @return boolean
     */
    function connect($server, $port, $login, $password) {
        $option = "/service=" . $this->_protocol;

        if ($this->_ssl) {
            $option .= "/ssl";
        }
        if ($this->_tls_on) {
            $option .= "/tls";
        } else {
            $option .= "/notls";
        }
        if ($this->_self_cert) {
            $option .= "/novalidate-cert";
        }
        if (preg_match("/google|gmail/i", $server)) {
            //Fix from Jim Hodgson http://www.jimhodgson.com/2006/07/19/postie/
            $this->_server_string = "{" . $server . ":" . $port . $option . "}INBOX";
//            if (IsDebugMode()) {
//                $this->_server_string = "{" . $server . ":" . $port . $option . "}[Gmail]/All Mail";
//            }
        } else {
            $this->_server_string = "{" . $server . ":" . $port . $option . "}";
        }
        $this->_connection = imap_open($this->_server_string, $login, $password);

        if ($this->_connection) {
            $this->_connected = true;
        } else {
            LogInfo("imap_open failed: " . imap_last_error());
        }
        return $this->_connected;
    }

    /**
     * Returns a count of the number of messages
     * @return integer
     */
    function getNumberOfMessages() {
        if (IsDebugMode()) {
            $status = imap_status($this->_connection, $this->_server_string, SA_ALL); //get all messages in debug mode so we can reprocess them
            //DebugEcho($this->_server_string);
            //DebugDump($status);
            if ($status)
                return $status->unseen;
            else {
                LogInfo("Error imap_status did not return a value");
                DebugDump($this);
                return 0;
            }
        } else {
            return imap_num_msg($this->_connection);
        }
    }

    /**
     * Gets the raw email message from the server
     * @return string
     */
    function fetchEmail($index) {
//        if ($index < 1 || $index > ($this->getNumberOfMessages() + 1)) {
//            die("Invalid IMAP/POP3 message index!");
//        }
        $header_info = imap_headerinfo($this->_connection, $index);

        if (IsDebugMode() || $header_info->Recent == 'N' || $header_info->Unseen == 'U') {
            $email = imap_fetchheader($this->_connection, $index);
            $email .= imap_body($this->_connection, $index);

            return $email;
        } else {
            return 'already read';
        }
    }

    /**
     * Marks a message for deletion
     */
    function deleteMessage($index) {
        imap_delete($this->_connection, $index);
    }

    /**
     * Handles purging any files that are marked for deletion
     */
    function expungeMessages() {
        imap_expunge($this->_connection);
    }

    /**
     * Handles disconnecting from the server
     */
    function disconnect() {
        imap_close($this->_connection);
        $this->_connection = false;
    }

    /**
     * @return string
     */
    function error() {
        return(imap_last_error());
    }

    /**
     * Handles returning the right kind of object
     * @return PostieIMAP|PostieIMAPSSL|PostimePOP3SSL
     * @static
     */
    function &Factory($protocol) {
        switch (strtolower($protocol)) {
            case "imap":
                $object = &new PostieIMAP();
                break;
            case "imap-ssl":
                $object = &new PostieIMAPSSL();
                break;
            case "pop3-ssl":
                $object = &new PostiePOP3SSL();
                break;
            default:
                die("$protocol not supported");
        }
        return($object);
    }

}

/**
 * This class handles the details of an IMAP-SSL connection
 *
 * @author Dirk Elmendorf
 * @package Postie
 */
class PostieIMAPSSL Extends PostieIMAP {

    function PostieIMAPSSL($protocol = "imap", $ssl_on = true, $self_cert = true) {
        PostieIMAP::PostieIMAP($protocol, $ssl_on, $self_cert);
    }

}

/**
 * This class handles the details of an POP3-SSL connection
 *
 * @author Dirk Elmendorf
 * @package Postie
 */
class PostiePOP3SSL Extends PostieIMAP {

    function PostiePOP3SSL($protocol = "pop3", $ssl_on = true, $self_cert = true) {
        PostieIMAP::PostieIMAP($protocol, $ssl_on, $self_cert);
    }

}

?>