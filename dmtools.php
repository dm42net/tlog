<?php

/*
*  Misc DM42 Utils
*
*
*/


Class DMXML {
    protected $verb;
    protected $attributes;
    protected $children;
    protected $singletag;

    public function __construct($verb,$singletag=false) {
        $this->singletag=$singletag;
        $this->verb=$verb;
        $this->attributes=Array();
        $this->children=Array();
    }

    public function set($attribute,$value=null) {
        if (is_array($attribute)) {
            foreach ($attribute as $realatt=>$val) {
                $this->set($realatt,$val);
            }
        } else {
            $this->attributes[$attribute]=$value;
        }
    }

    public function appendAttribute($attribute,$value,$separator=";") {
        $curvals=explode($separator,$this->attributes[$attribute]);
        if (count($curvals) > 0) {
            $this->attributes[$attribute].=$separator;
        }
        $this->attributes[$attribute].=$value;
    }

    public function addChild($child) {
        $this->children[]=$child;
    }

    public function __toString(){
        $retstring="<".$this->verb;
            foreach ($this->attributes as $attribute=>$value) {
                $retstring.=" ".$attribute."=\"".htmlentities($value)."\"";
            }
        if ($this->singletag) {
            $retstring.=" />";
        } else {
            $retstring.=">";
                foreach ($this->children as $child) {
                    if (is_object($child)) {
                        $retstring .= $child;
                    } else {
                        $retstring .= " ";
                        $retstring .= htmlentities($child);
                    }
                }
            $retstring.="</".$this->verb.">";
        }
        return $retstring;
      }

    

    }

    function dm42_addMeta($metavar,$metakey,$value,$multi=false) {
        if (!is_array($metavar)) {$metavar=Array();}
        
        if (!$multi) {
            $metavar[$metakey]=$value;
        } else {
            if ($multi) {
            $metavar[$metakey][]=$value;
            }
        }
        return $metavar;
    }

    function dm42_uniqid() {
        return base_convert(md5(mt_rand()).uniqid(true),16,36);
    }

    function dm42_get_meta_by_id ($table,$id,$colname="meta",$idcol="id") {
        $entry = \ORM::forTable($table)->
            where ($idcol,$id)->
            find_one();
        if ($entry) {
            return  dm42_maybe_unjson($entry->get($colname));
        } else {
            return null;
        }
    }
        
    function dm42_update_meta_by_id ($table,$id,$meta,$colname="meta",$idcol="id") {
        $entry = \ORM::forTable($table)->
            where ($idcol,$id)->
            find_one();
        if ($entry) {
            $entry->set($colname,dm42_maybe_json($meta));
            $entry->save();
            return true;
        }
    }

    function dm42_maybe_unjson ($value) {
        if ($value!=null) {
            $decoded=json_decode($value,true);
        } else {
            return null;
        }
        
        
        if (json_last_error() == JSON_ERROR_NONE) {
            return ($decoded);
        } else {
            return ($value);
        }
    }
    function dm42_maybe_json ($value) {
        if ($value!=null) {
            $decoded=json_decode($value,true);
        } else {
            return null;
        }
        
        if (is_array($value) || json_last_error() != JSON_ERROR_NONE) {
            return json_encode($value);
        } else {
            return ($value);
        }
    }

    function dm42_tableexists($table) {
        try {
            $table=ORM::forTable($table)->where_gt('id',0)->find_one();
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
