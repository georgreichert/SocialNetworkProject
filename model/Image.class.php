<?php
    include_once("Deleteable.interface.php");

    class Image implements Deleteable {
        private $database;
        private $row;

        function __construct(Database $database, int $postID = NULL, $row = array()) {
            $this->row = $row;
            $this->database = $database;
            //if $row is empty because Object was created outside the database, 
            //a new Object is created in the database, and its data is saved to $this->row
            if(!isset($this->row['Image_ID']))
                $this->row = $database->newImage($postID)->getAssoc();
        }

        //###################-----GETTERS-----###################

        public function getID () {
            return $this->row['Image_ID'];
        }

        public function getPostID () {
            return $this->row['Post_ID'];
        }

        public function getName () {
            return $this->row['Name'];
        }

        public function getPath () {
            return $this->row['Path'];
        }

        public function getThumbnailPath () {
            return $this->row['Thumbnail_path'];
        }

        public function getAssoc() {
            return $this->row;
        }

        //###################-----SETTERS-----###################

        public function setPostID ($id) {
            $this->row['Post_ID'] = $id;
            $this->database->updateImage($this);
        }
        
        public function setName ($name) {
            $this->row['Name'] = $name;
            $this->database->updateImage($this);
        }
        
        public function setPath ($path) {
            $this->row['Path'] = $path;
            $this->database->updateImage($this);
        }
        
        public function setThumbnailPath ($path) {
            $this->row['Thumbnail_path'] = $path;  
            $this->database->updateImage($this);
        }
        
        //###################-----OTHER-----###################

        public function delete () {
            $this->database->deleteObject($this);
        }
    }
?>