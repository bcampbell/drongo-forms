<?php

require_once 'widgets.php';


// holds either a list of error messages as strings,
// or a single error, with identifying code and params
class ValidationError extends Exception {
    public $messages = array();
    public $code=null;      // which error it is
    public $params=null;    // params for that error
    function __construct( $msg, $code=null, $params=null ) {
        if(is_array($msg))
            // a bunch of error strings
            $this->messages = $msg;
        else {
            // a single message, with enough info for somewhere
            // higher up to customise it
            $this->messages = array($msg);
            $this->code=$code;
            $this->params=$params;
        }
    }
}




class MinLengthValidator {

    public $msg = 'Ensure this value has at least %1$d characters (it has %2$d).';
    public $code = 'min_length';
    function __construct($limit) { $this->limit = $limit; }
    function execute($value) {
        $len=strlen($value);
        if($len < $this->limit) {
            $params = array($this->limit, $len);
            throw new ValidationError(vsprintf($this->msg,$params), $this->code, $params );
        }
    }
}

class MaxLengthValidator {

    public $msg = 'Ensure this value has at most %1$d characters (it has %2$d).';
    public $code = 'max_length';
    function __construct($limit) { $this->limit = $limit; }
    function execute($value) {
        $len=strlen($value);
        if($len > $this->limit) {
            $params = array($this->limit, $len);
            throw new ValidationError(vsprintf($this->msg,$params), $this->code, $params );
        }
    }
}


abstract class Field
{
    public $required = FALSE;
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
                $this->$optname = array_merge($this->optname,$opts[$optname]);
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
    public static function default_error_messages() {
        return array_merge( parent::default_error_messages(),
            array(
                'invalid'=>'Enter a whole number.',
                'max_value'=>'Ensure this value is less than or equal to %1$d.',
                'min_value'=>'Ensure this value is greater than or equal to %1$d.',
            ) );
    }
}

// TODO....
/* 
class IntegerField(Field):
    default_error_messages = {
        'invalid': _(u'Enter a whole number.'),
        'max_value': _(u'Ensure this value is less than or equal to %(limit_value)s.'),
        'min_value': _(u'Ensure this value is greater than or equal to %(limit_value)s.'),
    }

    def __init__(self, max_value=None, min_value=None, *args, **kwargs):
        super(IntegerField, self).__init__(*args, **kwargs)

        if max_value is not None:
            self.validators.append(validators.MaxValueValidator(max_value))
        if min_value is not None:
            self.validators.append(validators.MinValueValidator(min_value))

    def to_python(self, value):
        """
        Validates that int() can be called on the input. Returns the result
        of int(). Returns None for empty values.
        """
        value = super(IntegerField, self).to_python(value)
        if value in validators.EMPTY_VALUES:
            return None
        if self.localize:
            value = formats.sanitize_separators(value)
        try:
            value = int(str(value))
        except (ValueError, TypeError):
            raise ValidationError(self.error_messages['invalid'])
        return value
*/


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
        if(!array_key_exists($value,$this->choices)) {
            $msg = sprintf( $this->error_messages['invalid_choice'], $value);
            throw new ValidationError($msg);
        }
    }
}


class BooleanField extends Field {
    static function default_widget() { return new CheckboxInput(); }

    // TODO: implement proper to_php()
}

?>
