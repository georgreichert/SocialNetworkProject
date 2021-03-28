<?php
    include_once("Deleteable.interface.php");

    class User implements Deleteable{
        private $row;
        private $database;

        function __construct(Database $database, $row = array()) {
            $this->database = $database;
            $this->row = $row;
            //if $row is empty because Object was created outside the database, 
            //a new Object is created in the database, and its data is saved to $this->row
            if(!isset($this->row['User_ID']))
                $this->row = $database->newUser()->getAssoc();
        }

        //###################-----GETTERS-----###################

        public function getID () {
            return $this->row['User_ID'];
        }

        public function getGender() {
            return $this->row['Gender'];
        }

        public function getFirstName() {
            return $this->row['First_name'];
        }

        public function getName() {
            return $this->row['Name'];
        }

        public function getUsername() {
            return $this->row['Username'];
        }

        public function getEmail() {
            return $this->row['email'];
        }

        public function getAssoc() {
            return $this->row;
        }

        public function getPostList() {
            return $this->database->getPostList($this);
        }

        public function isActive() {
            return $this->row['Active'];
        }

        public function hasProfilePicture() {
            return $this->row['Profile_picture'];
        }

        //###################-----SETTERS-----###################

        public function setGender($new) {
            $this->row['Gender'] = $new;
            $this->database->updateUser($this);
        }

        public function setFirstName($new) {
            $this->row['First_name'] = $new;
            $this->database->updateUser($this);
        }

        public function setName($new) {
            $this->row['Name'] = $new;
            $this->database->updateUser($this);
        }

        //if Username already exists, false is returned and Name is not stored
        public function setUsername($new) {
            $temp = $this->row['Username'];
            $this->row['Username'] = $new;
            if(!$this->database->updateUser($this)) {
                $this->row['Username'] = $temp;
                return false;
            }
        }

        public function setPassword($new) {
            $this->database->changeUserPassword($this, $new);
        }

        public function setEmail($new) {
            $this->row['email'] = $new;
            $this->database->updateUser($this);
        }

        public function setActive(bool $status) {
            $this->row['Active'] = $status;
            $this->database->updateUser($this);
        }

        public function setProfilePicture (bool $status) {
            $this->row['Profile_picture'] = $status;
            $this->database->updateUser($this);
        }

        //###################-----OTHER-----###################

        //sets and returns new random password
        public function resetPassword() {
            $newPW = $this->generateStrongPassword();
            $this->setPassword($newPW);
            return $newPW;
        }

        public function isAdmin() {
            return $this->database->isAdmin($this);
        }

        public function delete () {
            $this->database->deleteObject($this);
        }

        public function vote (Voteable $voteable, $type) {
            $this->database->vote($this, $voteable, $type);
        }

        public function unvote(Voteable $voteable) {
            $this->database->unvote($this, $voteable);
        }

        public function hasVoted (Voteable $voteable) {
            return $this->database->hasVoted($this, $voteable);
        }
        
        // taken from: https://gist.github.com/tylerhall/521810
        private function generateStrongPassword($length = 9, $add_dashes = false, $available_sets = 'luds')
        {
            $sets = array();
            if(strpos($available_sets, 'l') !== false)
                $sets[] = 'abcdefghjkmnpqrstuvwxyz';
            if(strpos($available_sets, 'u') !== false)
                $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
            if(strpos($available_sets, 'd') !== false)
                $sets[] = '23456789';
            if(strpos($available_sets, 's') !== false)
                //$sets[] = '!@#$%&*?';
        
            $all = '';
            $password = '';
            foreach($sets as $set)
            {
                $password .= $set[array_rand(str_split($set))];
                $all .= $set;
            }
        
            $all = str_split($all);
            for($i = 0; $i < $length - count($sets); $i++)
                $password .= $all[array_rand($all)];
        
            $password = str_shuffle($password);
        
            if(!$add_dashes)
                return $password;
        
            $dash_len = floor(sqrt($length));
            $dash_str = '';
            while(strlen($password) > $dash_len)
            {
                $dash_str .= substr($password, 0, $dash_len) . '-';
                $password = substr($password, $dash_len);
            }
            $dash_str .= $password;
            return $dash_str;
        }
    }
?>