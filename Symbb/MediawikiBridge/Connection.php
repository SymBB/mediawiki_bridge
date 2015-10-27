<?php

namespace Symbb\MediawikiBridge;

class Connection {

    protected $host = 'localhost';
    protected $user;
    protected $password;
    protected $database;
    protected $prefix = 'phpbb_';
    protected $groups = array();
    protected $usernamePrefix = '';

    public function setHost($host) {
        $this->host = $host;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function setUsernameprefix($prefix) {
        $this->usernamePrefix = $prefix;
    }

    public function setPassword($password) {
        $this->password = $password;
    }

    public function setDatabase($database) {
        $this->database = $database;
    }

    public function setPrefix($prefix) {
        $this->prefix = $prefix;
    }

    public function setGroups($groups) {
        $this->groups = $groups;
    }

    public function getHost() {
        return $this->host;
    }

    public function getUser() {
        return $this->user;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getDatabase() {
        return $this->database;
    }

    public function getPrefix() {
        return $this->prefix;
    }

    public function getGroups() {
        return $this->groups;
    }

    public function getUserTable(){
        return $this->prefix.'users';
    }

    public function getUserGroupTable(){
        return $this->prefix.'user_group';
    }

    public function getGroupTable(){
        return $this->prefix.'groups';
    }

    public function getUsernamePrefix(){
        return $this->usernamePrefix;
    }
}