<?php

require_once 'widgets.php';
require_once 'validators.php';

// TODO:
// BUG: derived class default_error_messages() not being called?



abstract class Field
{
    public $required = TRUE;
    public $widget = null;
    public $label = null;
    public $initial = null;
    public $help_text = '';
    public $error_messages=null;
    public $show_hidden_initial = FALSE;
    public $validators=null;

    public $uniq=null;
    static private $creation_counter=0;

    static function default_error_messages() {
       return array(
        'required'=>'This field is required.',
        'invalid'=>'Enter a valid value.',
        );
    }

    static function default_validators() {
        return array();   // Default set of validators
    }

    static function default_widget() {
        return new TextInput();
    }

    static function default_hidden_widget() {
        return new HiddenInput();
    }


    public function __construct( $opts ) {
        // error_messages and validators can be extended/modified by opts
        $this->error_messages = self::default_error_messages();
        $this->validators = self::default_validators();

        foreach( array('required','widget','label','initial','help_text','error_messages','show_hidden_initial','validators') as $optname ) {
            if(!array_key_exists($optname,$opts) )
                continue;
            if(is_array($opts[$optname])) {
                $this->$optname = array_merge($this->$optname,$opts[$optname]);
            } else {
                $this->$optname = $opts[$optname];
            }
        }

        if(is_null($this->widget)) {
            $this->widget = $this->default_widget();
        }
        // widget can be a string containing name of the widget class
        if(is_string($this->widget)) {
            $this->widget = new $this->widget();
        }

        $extra_attrs = $this->widget_attrs($this->widget);
        if($extra_attrs) {
            $this->widget->attrs = array_merge(
                $this->widget->attrs, $extra_attrs);
        }

        $this->uniq = Field::$creation_counter++;
    }

    public function to_php($value) {
        return $value;
    }

    public function validate($value) {
        if( $this->required ) {
            if($value=='' or is_null($value)) {
                throw new ValidationError($this->error_messages['required']);
            }
        }
    }

    public function run_validators($value) {
        if($value=='' or is_null($value))
            return;
        $errors = array();
        foreach( $this->validators as $v ) {
            try {
                call_user_func($v,$value);
            } catch (ValidationError $e) {
                // TODO: if it's a single error with a code, we can
                // override it here with a field-specific message...
                $errors = $errors + $e->messages;
            }
        }

        if($errors)
            throw new ValidationError($errors);
    }

 
    public function clean($value) {
        $value = $this->to_php($value);
        $this->validate($value);
        $this->run_validators($value);
        return $value;
    }

    public function widget_attrs($widget) {
        return array();
    }

}





class CharField extends Field
{
    public $max_length=null;
    public $min_length=null;

    public function __construct( $opts ) {
        if(array_key_exists('max_length',$opts))
            $this->max_length=$opts['max_length'];
        if(array_key_exists('min_length',$opts))
            $this->min_length=$opts['min_length'];
        parent::__construct($opts);

        if($this->max_length)
            $this->validators[] = array(new MaxLengthValidator($this->max_length),'execute');
        if($this->min_length)
            $this->validators[] = array(new MinLengthValidator($this->min_length),'execute');
    }

    public function to_php($value) {
        if( !$value )
            return '';
        else
            return strval($value);
    }

    public function widget_attrs($widget) {
        if(!is_null($this->max_length)) {
            // TODO: should only add attr if widget is TextInput or PasswordInput
            // The HTML attribute is maxlength, not max_length.
            return array( 'maxlength'=>strval($this->max_length) );
        } else {
            return null;
        }
    }
}


class IntegerField extends Field {
    public $max_value = null;
    public $min_value = null;

    public static function default_error_messages() {
        return array_merge( parent::default_error_messages(),
            array(
                'invalid'=>'Enter a whole number.',
                'max_value'=>'Ensure this value is less than or equal to %1$s.',
                'min_value'=>'Ensure this value is greater than or equal to %1$s.',
            ) );
    }

    public function __construct( $opts ) {
        if(array_key_exists('max_value',$opts))
            $this->max_value=$opts['max_value'];
        if(array_key_exists('min_value',$opts))
            $this->min_value=$opts['min_value'];
        parent::__construct($opts);

        if($this->max_value)
            $this->validators[] = array(new MaxValueValidator($this->max_value),'execute');
        if($this->min_value)
            $this->validators[] = array(new MinValueValidator($this->min_value),'execute');
    }

    public function to_php($value) {
        if($value==='') {
            return null;
        }
        if(!is_numeric($value) || intval($value) != $value ) {
            throw new ValidationError($this->error_messages['invalid']);
        }
        return intval($value);
    }

}


class FloatField extends IntegerField {
    public static function default_error_messages() {
        return array_merge( parent::default_error_messages(),
            array( 'invalid'=>'Enter a number.',));
    }

    public function to_php($value) {
        if($value==='') {
            return null;
        }
        if(!is_numeric($value)) {
            throw new ValidationError($this->error_messages['invalid']);
        }
        return floatval($value);
    }
}


// TODO:
// DecimalField


class DateField extends Field {
    //static function default_widget() { return new DateInput(); }
    static function default_widget() { return new TextInput(); }
    public static function default_error_messages() {
        return array_merge(parent::default_error_messages(),
            array('invalid' => 'Enter a valid date.',));
    }

    public function __construct($opts) {
        parent::__construct($opts);
        $this->input_formats = array_key_exists('input_formats',$opts) ? $opts['input_formats'] : null;
        assert(is_null($this->input_formats));  // TODO: support custom input formats (might need php5.3+)?
    }

    /* returns a DateTime object */
    function to_php($value) {
        if(!$value)
            return null;
        if($value instanceof DateTime)
            return $value;
        if(is_array($value)) {
            // Input comes from a SplitDateTimeWidget, for example. So, it's two
            // components: date and time.
            assert(FALSE);  // TODO: implement
            return null;
        }

        // TODO: parse custom input formats here...
        try {
            return new DrongoDate($value);
        } catch (Exception $e) {
        }

        throw new ValidationError($this->error_messages['invalid']);
    }
}





// TODO:
// TimeField
// DateTimeField

//
// TODO: redo FileField - should be based on ClearableFileInput
class FileField extends Field {
    static function default_widget() { return new FileInput(); }
    public static function default_error_messages() {
        return array_merge(parent::default_error_messages(), array(
            'invalid'=>"No file was submitted. Check the encoding type on the form.",
            'missing'=>"No file was submitted.",
            'empty'=>"The submitted file is empty.",
            'max_length'=>'Ensure this filename has at most %1$s characters (it has %2$s).',
            'oversize'=>'File was too large',
            'upload_failed'=>'Upload failed (error code: %1$d)',
            //'contradiction'=>'Please either submit a file or check the clear checkbox, not both.'
            // TODO: add better error messages for PHP file upload errors
        ));
    }

    public function __construct($opts) {
        parent::__construct($opts);

        $this->error_messages = $this->default_error_messages();
        $this->max_length = array_key_exists('max_length',$opts) ? $opts['max_length'] : null;
        $this->allow_empty_file = array_key_exists('allow_empty_file',$opts) ? $opts['allow_empty_file'] : FALSE;
    }

    public function to_php($data) {

        if(!is_array($data)) {
            return null;
        }
        if(count($data)==0) {
            return null;
        }

        $err = $data['error'];
        if($err==UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if($err==UPLOAD_ERR_INI_SIZE || $err==UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationError($this->error_messages['oversize']);
        }

        if($err!=UPLOAD_ERR_OK) {
            $msg = sprintf( $this->error_messages['upload_failed'], $err);
            throw new ValidationError($msg);
        }


        // Uploaded File objects should have name and size attributes (at least)
        if(!array_key_exists('name',$data) || !array_key_exists('size',$data) ) {
            throw new ValidationError($this->error_messages['invalid']);
        }

        $file_name = $data['name'];
        $file_size = $data['size'];
        if(!is_null($this->max_length) and strlen($file_name) > $this->max_length) {
            $msg = sprintf( $this->error_messages['max_length'], $this->max_length, strlen($file_name));
            throw new ValidationError($msg);
        }
        if(!$file_name) {
            throw new ValidationError($this->error_messages['invalid']);
        }
        if(!$this->allow_empty_file && !$file_size) {
            throw new ValidationError($this->error_messages['empty']);
        }

        return $data;
    }
}




// TODO:
// ImageField

class RegexField extends CharField {

    public function __construct($regex,$opts) {
        parent::__construct($opts);
        $this->validators[] = array(new RegexValidator($regex),'execute');
    }
}

class EmailField extends CharField {
    public static function default_error_messages() {
        return array_merge( parent::default_error_messages(),
            array( 'invalid'=>'Enter a valid e-mail address.',));
    }

    public function __construct($opts) {
        parent::__construct($opts);
        $this->validators[] = array(new EmailValidator(),"execute");
    }
}



class URLField extends CharField {
    // TODO: verify_exists not supported
    public static function default_error_messages() {
        return array_merge( parent::default_error_messages(),
            array( 'invalid'=>'Enter a valid URL.',));
        //'invalid_link': _(u'This URL appears to be a broken link.'),
    }

    public function __construct($opts) {
        parent::__construct($opts);
        $this->validators[] = array(new URLValidator(),"execute");
    }

    public function to_php($value) {
        if($value) {
            if(strpos('://',$value) !== FALSE ) {
                /* If no URL scheme given, assume http:// */
                $value = 'http://' . $value;
            }
            // TODO: other url fixups:
/*
            url_fields = list(urlparse.urlsplit(value))
            if not url_fields[2]:
                # the path portion may need to be added before query params
                url_fields[2] = '/'
                value = urlparse.urlunsplit(url_fields)
*/
        }
        return parent::to_php($value);
    }
}

class BooleanField extends Field {
    static function default_widget() { return new CheckboxInput(); }

    /* returns a boolean */
    function to_php($value) {
        $l = strtolower($value);

        /* Explicitly check for the string 'False', which is what a hidden field
         will submit for False. Also check for '0', since this is what
          RadioSelect will provide.
          . Because (bool)"true" == (bool)'1' == TRUE,
         we don't need to handle that explicitly.
         */
        if($l==='false' or $l==='0')
            $value=FALSE;
        else
            $value=(bool)$value;
        return $value;
    }
}


// TODO:
// NullBooleanField


class ChoiceField extends Field {
    static function default_widget() { return new Select(); }
    static function default_error_messages() {
        return array_merge( parent::default_error_messages(),
            array( 'invalid_choice'=>'Select a valid choice. %1$s is not one of the available choices.') );
    }

    private $_choices=array();
    public function __construct( $opts ) {
        parent::__construct($opts);
        $this->choices = $opts['choices'];
    }

    function __get($name) {
        if($name=='choices')
            return $this->_choices;
    }

    function __set($name, $value) {
        if($name=='choices') {
            // Setting choices also sets the choices on the widget.
            $this->_choices = $value;
            $this->widget->choices = $value;
        }
    }

    // returns a string
    function to_php($value) {
        if( !$value )
            return '';
        return $value;
    }
    
    // Validates that the input is in self.choices.
    function validate($value) {
        parent::validate($value);
        if(!$value)
            return; # blank is ok
        if(!$this->valid_value($value)) {
            $msg = sprintf( $this->error_messages['invalid_choice'], $value);
            throw new ValidationError($msg);
        }
    }

    // Check to see if the provided value is a valid choice
    function valid_value($value) {
        foreach($this->choices as $k=>$v) {
            if(is_array($v)) {
                // This is an optgroup, so look inside the group for options
                foreach($v as $k2=>$v2) {
                    if($value == $k2) {
                        return TRUE;
                    }
                }
            } else {
                if($value==$k) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

}

// TODO:
// TypedChoiceField



class MultipleChoiceField extends ChoiceField {
    static function default_hidden_widget() { return new MultipleHiddenInput(); }
    static function default_widget() { return new SelectMultiple(); }
    static function default_error_messages() {
        return array_merge( parent::default_error_messages(), array(
            'invalid_choice'=>'Select a valid choice. %1$s is not one of the available choices.',
            'invalid_list'=>'Enter a list of values.'
        ));
    }

    function to_php($value)
    {
        if(!$value) {
            return array();
        }
        if(!is_array($value)) {
            throw new ValidationError($this->error_messages['invalid_list']);
        }
        return $value;
    }

    function validate($value)
    {
        if($this->required and !$value)
            throw new ValidationError($this->error_messages['required']);
        // Validate that each value in the value list is a valid choice.
        foreach($value as $val) {
            if(!$this->valid_value($val)){
                $msg = sprintf( $this->error_messages['invalid_choice'], $val);
                throw new ValidationError($msg);
            }
        }
    }

}


// TODO:
// ComboField
// MultiValueField
// FilePathField
// SplitDateTimeField
// IPAddressField
// SlugField


?>
