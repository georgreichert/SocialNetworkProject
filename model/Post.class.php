<?php
    include_once(dirname(__FILE__) . "/../model/Deleteable.interface.php");
    include_once(dirname(__FILE__) . "/../model/Voteable.interface.php");
    include_once(dirname(__FILE__) . "/../model/Uniteable.interface.php");

    class Post implements Deleteable, Voteable, Uniteable{
        private $database;
        private $row;

        function __construct(Database $database, int $userID = NULL, $row = array()) {
            $this->row = $row;
            $this->database = $database;
            //if $row is empty because Object was created outside the database, 
            //a new Object is created in the database, and its data is saved to $this->row
            if(!isset($this->row['Post_ID']))
                $this->row = $database->newPost($userID)->getAssoc();
        }

        //###################-----GETTERS-----###################

        public function getID () {
            return $this->row['Post_ID'];
        }

        public function getUserID () {
            return $this->row['User_ID'];
        }

        public function getAuthor () : User {
            return $this->database->getUserByID($this->getUserID());
        }

        public function getTitle () {
            return $this->row['Title'];
        }

        public function getText () {
            return $this->row['Text'];
        }

        // returns the posts text, but exchanges every #hashtag with a link for #hashtag search
        public function getHashtaggedText () {
            return preg_replace("/(#(\w+))/u", "<a href=\"index.php?searchValue=%23$2\">$0</a>", $this->row['Text']);
        }

        public function getVisibility () {
            return $this->row['Visibility'];
        }

        public function getDate () {
            return $this->row['Date'];
        }

        public function getAssoc() {
            return $this->row;
        }

        public function getImage() {
            return $this->database->getImage($this->getID());
        }

        //type: true for likes, false for dislikes
        public function getVotes(bool $type) {
            return $this->database->getVotes($this, $type);
        }

        public function getCommentList () {
            return $this->database->getCommentList($this);
        }

        public function getVoteCount (bool $type) {
            return $this->database->getVoteCount($type, $this);
        }

        public function hasVoted (User $user) {
            return $this->database->hasVoted($user, $this);
        }

        //###################-----SETTERS-----###################

        public function setTitle ($title) {
            $this->row['Title'] = $title;
            $this->database->updatePost($this);
        }
        
        public function setText ($text) {
            $this->row['Text'] = $text;
            $this->database->updatePost($this);
            $this->database->readHashtags($this);
        }
        
        public function setUserID ($id) {
            $this->row['User_ID'] = $id;
            $this->database->updatePost($this);
        }
        
        public function setVisibility ($visibility) {
            $this->row['Visibility'] = $visibility;
            $this->database->updatePost($this);
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