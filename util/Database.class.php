<?php
    include_once(dirname(__FILE__) . "/../model/User.class.php");
    include_once(dirname(__FILE__) . "/../model/Post.class.php");
    include_once(dirname(__FILE__) . "/../model/Comment.class.php");
    include_once(dirname(__FILE__) . "/../model/Image.class.php");
    include_once(dirname(__FILE__) . "/../util/filterForHashtags.function.php");

    class Database {
        private $connection;
        const STANDARD_USER_SEARCH_STRING = "User_ID, Gender, First_name, Name, Username, email, Active, Profile_picture";
        
        function __construct () {
            //retrieve username/password from config file
            $userdata = fopen(dirname(__FILE__) . "/../setup/userdata.config", "r");
            $username = rtrim(fgets($userdata));
            $password = rtrim(fgets($userdata));
            fclose($userdata);
            //setup database connection
            if(!$this->connection = @mysqli_connect("localhost", $username, $password, "social_network")) {
                throw new Exception("Verbindung zur Datenbank konnte nicht aufgebaut werden.");
            }
        }

        function __destruct() {
            $this->connection->close();
        }

        //###################-----GET OBJECTS-----###################

        //returns all user data except password
        public function getUserByName ($username) {
            $query = $this->connection->prepare("SELECT ".SELF::STANDARD_USER_SEARCH_STRING." 
                FROM Users WHERE Username = ?");
            $query->bind_param("s", $username);
            $query->execute();
            $result = $query->get_result();
            //returns false if no row was found
            if ($row = $result->fetch_assoc()) {
                return new User($this, $row);
            } else {
                return false;
            }
        }

        public function getUserByEmail ($email) {
            $query = $this->connection->prepare("SELECT ".SELF::STANDARD_USER_SEARCH_STRING." 
                FROM Users WHERE email = ?");
            $query->bind_param("s", $email);
            $query->execute();
            $result = $query->get_result();
            //returns false if no row was found
            if ($row = $result->fetch_assoc()) {
                return new User($this, $row);
            } else {
                return false;
            }
        }

        //returns all user data except password
        public function getUserByID ($id) {
            $query = $this->connection->prepare("SELECT ".SELF::STANDARD_USER_SEARCH_STRING." 
                FROM Users WHERE User_ID = ?");
            $query->bind_param("i", $id);
            $query->execute();
            $result = $query->get_result();
            //returns false if no row was found
            if ($row = $result->fetch_assoc()) {
                return new User($this, $row);
            } else {
                return false;
            }
        }

        public function getPostByID ($id) {
            $query = $this->connection->prepare("SELECT * FROM Posts WHERE Post_ID = ?");
            $query->bind_param("i", $id);
            $query->execute();
            $result = $query->get_result();
            //returns false if no row was found
            if ($row = $result->fetch_assoc()) {
                return new Post($this, NULL, $row);
            } else {
                return false;
            }
        }

        public function getCommentByID ($id) {
            $query = $this->connection->prepare("SELECT * FROM Comments WHERE Comment_ID = ?");
            $query->bind_param("i", $id);
            $query->execute();
            $result = $query->get_result();
            //returns false if no row was found
            if ($row = $result->fetch_assoc()) {
                return new Comment($this, NULL, NULL, $row);
            } else {
                return false;
            }
        }

        public function getImage ($postID) {
            $query = $this->connection->prepare("SELECT * FROM Images WHERE Post_ID = ?");
            $query->bind_param("i", $postID);
            $query->execute();
            $result = $query->get_result();
            //returns false if no row was found
            if ($row = $result->fetch_assoc()) {
                return new Image($this, NULL, $row);
            } else {
                return false;
            }
        }

        //returns array of all users
        public function getUserList () {
            $query = $this->connection->prepare("SELECT ".SELF::STANDARD_USER_SEARCH_STRING." 
                FROM Users");
            $query->execute();
            $result = $query->get_result();
            //add all results to array $users
            $users = array();
            while ($row = $result->fetch_assoc()) {
                array_push($users, new User($this, $row));
            }
            return $users;
        }

        //returns array of all posts in reverse order, starting at the newest
        //if User is given, only returns Posts of given User
        public function getPostList (User $user = NULL) {
            if ($user != NULL) {
                $query = $this->connection->prepare("SELECT * FROM Posts WHERE User_ID = ? ORDER BY Date DESC");
                $id = $user->getID();
                $query->bind_param("i", $id);
            } else {
                $query = $this->connection->prepare("SELECT * FROM Posts ORDER BY Date DESC");
            }
            $query->execute();
            $result = $query->get_result();
            //add all results to array $posts
            $posts = array();
            while ($row = $result->fetch_assoc()) {
                array_push($posts, new Post($this, NULL, $row));
            }
            return $posts;
        }

        //returns array of all posts containing the searched for hashtags in reverse order, starting at the newest
        public function getPostListByHashtags (array $tagArray) {
            $allPosts = array();
            foreach ($tagArray as $i=>$hashtag) {
                $query = $this->connection->prepare("SELECT Post_ID FROM Post_Hashtag WHERE Hashtag = ?");
                $query->bind_param("s", $hashtag);
                $query->execute();
                $result = $query->get_result();
                $posts = array();
                while($row = $result->fetch_assoc()) {
                    array_push($posts, $this->getPostByID($row['Post_ID']));
                }
                if ($i == 0) {
                    $allPosts = $this->union($allPosts, $posts);
                } else {
                    $allPosts = $this->intersection($allPosts, $posts);
                }
            }
            usort($allPosts, function(Post $a, Post $b) {
                    return $b->getID()<$a->getID()?-1:($b->getID()==$a->getID()?0:1);
                });
            return $allPosts;
        }

        //returns array of all comments under $post in normal order, starting at the oldest
        public function getCommentList (Post $post) {
            $query = $this->connection->prepare("SELECT * FROM Comments WHERE Post_ID = ? ORDER BY Date ASC");
            $id = $post->getID();
            $query->bind_param("i", $id);
            $query->execute();
            $result = $query->get_result();
            //add all results to array $comments
            $comments = array();
            while ($row = $result->fetch_assoc()) {
                array_push($comments, new Comment($this, NULL, NULL, $row));
            }
            return $comments;
        }

        //returns Union of both arrays of Uniteables (only Posts atm)
        private function union(array $arr1, array $arr2) {
            if(!((sizeof($arr1) == 0) || (sizeof($arr2) == 0))) {
                if((get_class($arr1[0]) != get_class($arr2[0])) 
                    || !($arr1[0] instanceof Uniteable)) {
                    return false;
                }
            }
            foreach ($arr1 as $uniteable1) {
                $isIn = false;
                foreach ($arr2 as $uniteable2) {
                    if($uniteable2->getID() == $uniteable1->getID()) {
                        $isIn = true;
                        break;
                    }
                }
                if (!$isIn) {
                    array_push($arr2, $uniteable1);
                }
            }
            return $arr2;
        }

        // returns array of all Objects in $arr1 that are not in $arr2
        private function difference (array $arr1, array $arr2) {
            $result = array();
            if(!((sizeof($arr1) == 0) || (sizeof($arr2) == 0))) {
                if((get_class($arr1[0]) != get_class($arr2[0])) 
                    || !($arr1[0] instanceof Uniteable)) {
                    return false;
                }
            }
            foreach ($arr1 as $uniteable1) {
                $isIn = false;
                foreach ($arr2 as $uniteable2) {
                    if($uniteable2->getID() == $uniteable1->getID()) {
                        $isIn = true;
                        break;
                    }
                }
                if (!$isIn) {
                    array_push($result, $uniteable1);
                }
            }
            return $result;
        }

        //returns array of all objects that are in both input arrays
        private function intersection (array $arr1, array $arr2) {
            $result = array();
            if(!((sizeof($arr1) == 0) || (sizeof($arr2) == 0))) {
                if((get_class($arr1[0]) != get_class($arr2[0])) 
                    || !($arr1[0] instanceof Uniteable)) {
                    return false;
                }
            }
            foreach ($arr1 as $uniteable1) {
                $isIn = false;
                foreach ($arr2 as $uniteable2) {
                    if($uniteable2->getID() == $uniteable1->getID()) {
                        $isIn = true;
                        break;
                    }
                }
                if ($isIn) {
                    array_push($result, $uniteable1);
                }
            }
            return $result;
        }

        //returns array of arrays of search results, ordered by category
        public function search (string $string) {
            $results = array();
            $results['Users'] = $this->userSearch($string);
            $results['Hashtags'] = $this->getPostListByHashtags(filterForHashtags($string));
            $results['Text_Posts'] = $this->difference($this->textSearch($string, "Posts"), $results['Hashtags']);
            $results['Text_Comments'] = $this->textSearch($string, "Comments");
            return $results;
        }

        //searches for users with names similar to $string
        private function userSearch (string $string) {
            $query = $this->connection->prepare("SELECT ".SELF::STANDARD_USER_SEARCH_STRING." 
                FROM Users WHERE Username LIKE ?");
            $string = "%".$string."%";
            $query->bind_param("s", $string);
            $query->execute();
            $result = $query->get_result();
            //returns false if no row was found
            $users = array();
            while ($row = $result->fetch_assoc()) {
                array_push($users, new User($this, $row));
            }
            return $users;
        }

        //searches for posts or comments with occurence of $string in Text or Title
        private function textSearch (string $string, string $option) {
            $string = "%".$string."%";
            if($option == "Posts") {
                $query = $this->connection->prepare("SELECT * FROM Posts WHERE Text LIKE ? OR Title LIKE ? ORDER BY Date DESC");
                $query->bind_param("ss", $string, $string);
            } else if ($option == "Comments") {
                $query = $this->connection->prepare("SELECT * FROM Comments WHERE Text LIKE ? ORDER BY Date DESC");
                $query->bind_param("s", $string);
            }
            echo $this->connection->error;
            $query->execute();
            $result = $query->get_result();
            //add all results to array $posts
            $found = array();
            while ($row = $result->fetch_assoc()) {
                if($option == "Posts") {
                    array_push($found, new Post($this, NULL, $row));
                } else if ($option == "Comments") {
                    array_push($found, new Comment($this, NULL, NULL, $row));
                }
            }
            return $found;
        }

        //###################-----OBJECT CREATION-----###################

        public function newUser () {
            //create a new user with unique id, all other values are NULL
            $query = $this->connection->prepare("INSERT INTO Users (Gender) VALUES (NULL)");
            $query->execute();
            //fetch the user with the newest User_ID, which is the just created blank user
            $query = $this->connection->prepare("SELECT ".SELF::STANDARD_USER_SEARCH_STRING." 
                FROM Users WHERE User_ID = LAST_INSERT_ID()");
            $query->execute();
            $result = $query->get_result();
            $row = $result->fetch_assoc();
            return new User($this, $row);
        }

        public function newPost (int $userID = NULL) {
            //create a new post with unique id, UserID optional, all other values are NULL
            $query = $this->connection->prepare("INSERT INTO Posts (User_ID) VALUES (?)");
            $query->bind_param("i", $userID);
            //return false if foreign User_ID doesn't exist
            if(!$query->execute())
                return false;
            //fetch the post with the newest Post_ID, which is the just created blank post
            $query = $this->connection->prepare("SELECT * FROM Posts WHERE Post_ID = LAST_INSERT_ID()");
            $query->execute();
            $result = $query->get_result();
            $row = $result->fetch_assoc();
            return new Post($this, $userID, $row);
        }

        public function newComment (int $postID = NULL, int $userID = NULL) {
            //create a new comment with unique id, PostID/UserID optional, all other values are NULL
            $query = $this->connection->prepare("INSERT INTO Comments (Post_ID, User_ID) VALUES (?,?)");
            $query->bind_param("ii", $postID, $userID);
            //return false if foreign User_ID doesn't exist
            if(!$query->execute())
                return false;
            //fetch the comment with the newest Comment_ID, which is the just created blank comment
            $query = $this->connection->prepare("SELECT * FROM Comments WHERE Comment_ID = LAST_INSERT_ID()");
            $query->execute();
            $result = $query->get_result();
            $row = $result->fetch_assoc();
            return new Comment($this, $postID, $userID, $row);
        }

        public function newImage (int $postID = NULL) {
            //create a new user with unique id, User_ID optional, all other values are NULL
            $query = $this->connection->prepare("INSERT INTO Images (Post_ID) VALUES (?)");
            $query->bind_param("i", $postID);
            //return false if foreign User_ID doesn't exist
            if(!$query->execute())
                return false;
            //fetch the image with the newest Image_ID, which is the just created blank image
            $query = $this->connection->prepare("SELECT * FROM Images WHERE Image_ID = LAST_INSERT_ID()");
            $query->execute();
            $result = $query->get_result();
            $row = $result->fetch_assoc();
            return new Image($this, $postID, $row);
        }

        //###################-----DATA MANIPULATION-----###################

        //save changes to User object in database (except password)
        public function updateUser (User $user) {
            $row = $user->getAssoc();
            $query = $this->connection->prepare("UPDATE Users SET
                Gender = ?, First_name = ?, Name = ?, 
                Username = ?, email = ?, Active = ?, Profile_picture = ?
                WHERE User_ID = ?");
            $query->bind_param("sssssiii", 
                $row['Gender'],
                $row['First_name'],
                $row['Name'],
                $row['Username'],
                $row['email'],
                $row['Active'],
                $row['Profile_picture'],
                $row['User_ID']
                );
            return $query->execute();
        }

        //changes password to $new and stores hash in database
        public function changeUserPassword(User $user, $new) {
            $newPW = password_hash($new, PASSWORD_DEFAULT);
            $query = $this->connection->prepare("UPDATE Users SET
            Password = ?
            WHERE User_ID = ?");
            $id = $user->getID();
            $query->bind_param("si", $newPW, $id);
            $query->execute();
        }

        //save changes to Post object in database
        public function updatePost (Post $post) {
            $row = $post->getAssoc();
            $query = $this->connection->prepare("UPDATE Posts SET
                User_ID = ?, Title = ?, Text = ?, Visibility = ?
                WHERE Post_ID = ?");
            $query->bind_param("isssi", 
                $row['User_ID'],
                $row['Title'],
                $row['Text'],
                $row['Visibility'],
                $row['Post_ID']
                );
            $query->execute();
        }

        //save changes to Comment object in database
        public function updateComment (Comment $comment) {
            $row = $comment->getAssoc();
            $query = $this->connection->prepare("UPDATE Comments SET
                Post_ID = ?, User_ID = ?, Text = ?
                WHERE Comment_ID = ?");
                echo $this->connection->error;
            $query->bind_param("iisi",
                $row['Post_ID'], 
                $row['User_ID'],
                $row['Text'],
                $row['Comment_ID']
                );
            return $query->execute();
        }

        //save changes to Image object in database
        public function updateImage (Image $image) {
            $row = $image->getAssoc();
            $query = $this->connection->prepare("UPDATE Images SET
                Post_ID = ?, Path = ?, Thumbnail_path = ?
                WHERE Image_ID = ?");
                echo $this->connection->error;
            $query->bind_param("issi",
                $row['Post_ID'], 
                $row['Path'],
                $row['Thumbnail_path'],
                $row['Image_ID']
                );
            return $query->execute();
        }

        //###################-----DATA DELETION-----###################

        //deletes the entry in database linked to $toDelete
        public function deleteObject(Deleteable $toDelete) {
            if ($toDelete instanceof User) {
                $type = "User";
            } else if ($toDelete instanceof Post) {
                $type = "Post";
            } else if ($toDelete instanceof Comment) {
                $type = "Comment";
            }else if ($toDelete instanceof Image) {
                $type = "Image";
            }
            $query = $this->connection->prepare("DELETE FROM ".$type."s WHERE ".$type."_ID = ?");
            $id = $toDelete->getID();
            $query->bind_param("i", $id);
            return $query->execute();
        }

        //###################-----CHECKS-----###################

        //checks if combination of $username and $password is legit, returns logged in user if true
        public function loginUser ($username, $password) {
            $query = $this->connection->prepare("SELECT Username, Password FROM Users WHERE Username = ?");
            $query->bind_param("s", $username);
            $query->execute();
            $result = $query->get_result();
            if ($row = $result->fetch_assoc()) {
                return password_verify($password, $row['Password'])?$this->getUserByName($username):false;
            }
            return false;
        }

        public function isAdmin (User $user) {
            $id = $user->getID();
            $query = $this->connection->prepare("SELECT User_ID FROM Users JOIN Admins USING(User_ID) WHERE User_ID = ?");
            $query->bind_param("i", $id);
            $query->execute();
            $result = $query->get_result();
            //returns false if no row was found
            if ($row = $result->fetch_assoc()) {
                return true;
            } else {
                return false;
            }
        }

        //###################-----VOTES-----###################

        public function getVotes(Voteable $voteable, bool $type) {
            if ($voteable instanceof Post) {
                $queryType = "Post";
            } else if ($voteable instanceof Comment) {
                $queryType = "Comment";
            }
            $query = $this->connection->prepare("SELECT ".SELF::STANDARD_USER_SEARCH_STRING." 
                FROM Users JOIN ".$queryType."_Vote USING(User_ID) WHERE Type = ? AND ".$queryType."_ID = ?");
            $id = $voteable->getID();
            $query->bind_param("ii", $type, $id);
            $query->execute();
            $result = $query->get_result();
            //add all results to array $users
            $users = array();
            while ($row = $result->fetch_assoc()) {
                array_push($users, new User($this, $row));
            }
            return $users;
        }

        public function vote (User $user, Voteable $voteable, bool $type) {
            if ($voteable instanceof Post) {
                $queryType = "Post";
            } else if ($voteable instanceof Comment) {
                $queryType = "Comment";
            }
            $query = $this->connection->prepare("INSERT INTO ".$queryType."_Vote VALUES (?,?,?)");
            $vID = $voteable->getID();
            $uID = $user->getID();
            $query->bind_param("iii", $vID, $uID, $type);
            $query->execute();
        }

        public function unvote (User $user, Voteable $voteable) {
            if ($voteable instanceof Post) {
                $queryType = "Post";
            } else if ($voteable instanceof Comment) {
                $queryType = "Comment";
            }
            $query = $this->connection->prepare("DELETE FROM ".$queryType."_Vote 
                WHERE ".$queryType."_ID = ? AND  User_ID = ?");
            $vID = $voteable->getID();
            $uID = $user->getID();
            $query->bind_param("ii", $vID, $uID);
            $query->execute();
        }

        public function getVoteCount(bool $type, Voteable $voteable) {
            if ($voteable instanceof Post) {
                $queryType = "Post";
            } else if ($voteable instanceof Comment) {
                $queryType = "Comment";
            }
            $query = $this->connection->prepare("SELECT COUNT(*) as Count FROM 
                ".$queryType."_Vote WHERE Type = ? AND ".$queryType."_ID = ?");
            $id = $voteable->getID();
            $query->bind_param("ii", $type, $id);
            $query->execute();
            $result = $query->get_result();
            return $result->fetch_assoc()['Count'];
        }

        //returns -1 for dislike, 0 for not voted, 1 for like
        public function hasVoted (User $user, Voteable $voteable) {
            if ($voteable instanceof Post) {
                $queryType = "Post";
            } else if ($voteable instanceof Comment) {
                $queryType = "Comment";
            }
            $query = $this->connection->prepare("SELECT Type FROM 
                ".$queryType."_Vote WHERE User_ID = ? AND ".$queryType."_ID = ?");
            $userID = $user->getID();
            $postID = $voteable->getID();
            $query->bind_param("ii", $userID, $postID);
            $query->execute();
            $result = $query->get_result();
            if($row = $result->fetch_assoc()) {
                return $row['Type']?1:-1;
            } else {
                return 0;
            }
        }

        //###################-----HASHTAGS-----###################
        
        public function readHashtags (Voteable $voteable) {
            if ($voteable instanceof Post) {
                $queryType = "Post";
            } else if ($voteable instanceof Comment) {
                $queryType = "Comment";
            }
            $query = $this->connection->prepare("DELETE FROM ".$queryType."_Hashtag WHERE ".$queryType."_ID = ?");
            $query->bind_param("i", $id);
            $query->execute();
            foreach (filterForHashtags($voteable->getText()) as $tag) {
                $query = $this->connection->prepare("INSERT INTO ".$queryType."_Hashtag VALUES (?,?)");
                $id = $voteable->getID();
                $query->bind_param("is", $id, $tag);
                $query->execute();
            }
        }
    }
?>