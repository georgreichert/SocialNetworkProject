<?php
    include_once(dirname(__FILE__) . "/../model/Deleteable.interface.php");
    include_once(dirname(__FILE__) . "/../model/Voteable.interface.php");

    class Comment implements Deleteable, Voteable {
        private $database;
        private $row;

        function __construct(Database $database, int $postID = NULL, int $userID = NULL, $row = array()) {
            $this->row = $row;
            $this->database = $database;
            //if $row is empty because Object was created outside the database, 
            //a new Object is created in the database, and its data is saved to $this->row
            if(!isset($this->row['Comment_ID']))
                $this->row = $database->newComment($postID, $userID)->getAssoc();
        }

        //###################-----GETTERS-----###################

        public function getID () {
            return $this->row['Comment_ID'];
        }

        public function getPostID () {
            return $this->row['Post_ID'];
        }

        public function getPost () : Post {
            return $this->database->getPostByID($this->getPostID());
        }

        public function getUserID () {
            return $this->row['User_ID'];
        }

        public function getAuthor () : User {
            return $this->database->getUserByID($this->getUserID());
        }

        public function getText () {
            return $this->row['Text'];
        }

        public function getDate () {
            return $this->row['Date'];
        }

        public function getAssoc() {
            return $this->row;
        }

        //type: true for likes, false for dislikes
        public function getVotes(bool $type) {
            return $this->database->getVotes($this, $type);
        }

        public function getVoteCount (bool $type) {
            return $this->database->getVoteCount($type, $this);
        }

        public function hasVoted (User $user) {
            return $this->database->hasVoted($user, $this);
        }

        //###################-----SETTERS-----###################

        public function setPostID ($id) {
            $this->row['Post_ID'] = $id;
            $this->database->updateComment($this);
        }
        
        public function setUserID ($id) {
            $this->row['User_ID'] = $id;
            $this->database->updateComment($this);
        }
        
        public function setText ($text) {
            $this->row['Text'] = $text;
            $this->database->updateComment($this);
        }
        
        //###################-----OTHER-----###################

        public function delete () {
            $this->database->deleteObject($this);
        }

        public function vote (User $user, $type) {
            $this->database->vote($user, $this, $type);
        }
        
        public function unvote (User $user) {
            $this->database->unvote($user, $this);
        }
    }
?>