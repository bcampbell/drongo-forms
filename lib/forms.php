<?php
require_once 'fields.php';
require_once 'widgets.php';

define( 'NON_FIELD_ERRORS', '__all__' );

// TODO:
//  sort out form rendering
//  support media (css,js)

class BaseForm
{
    public function __construct($data=null, $files=null, $opts ) {
        $default_opts = array(
            'auto_id'=>'id_%s',
            'prefix'=>null,
            'initial'=>array(),
            /* 'error_class'=>ErrorList, */
            'label_suffix'=>':',
            'empty_permitted'=>FALSE, );

        $opts = array_merge($default_opts,$opts);

        $this->is_bound = ($data || $files) ? TRUE:FALSE;
        $this->data = is_null($data) ? array() : $data;
        $this->files = is_null($files) ? array() : $files;

        $this->auto_id = $opts['auto_id'];
        $this->prefix = $opts['prefix'];
        $this->initial = $opts['initial'];
        /*$this->error_class = $opts['error_class'];*/
        $this->label_suffix = $opts['label_suffix'];
        $this->empty_permitted = $opts['empty_permitted'];


        $this->_changed_data = null;

        $this->fields = array();
        $this->_errors = null;
    }



    public function __get($v) {
        if( $v=='errors' )
            return $this->_get_errors();
        if( $v=='changed_data' )
            return $this->_get_changed_data();
        //TODO media

        // TODO: should trigger an error here?
        return null;
    }

    /* Returns an ErrorDict for the data provided for the form */
    private function _get_errors() {
        if(is_null($this->_errors)) {
            $this->full_clean();
        }
        return $this->_errors;
    }

    /*
        Returns True if the form has no errors. Otherwise, False. If errors are
        being ignored, returns False.
    */
    public function is_valid() {
        // TODO: 
        return( $this->is_bound && !$this->errors );
    }


    /*
    Returns the field name with a prefix appended, if this Form has a
    prefix set.

    Subclasses may wish to override.
    */
    public function add_prefix($field_name) {
        if($this->prefix) {
            return sprintf('%s-%s', $this->prefix, $field_name);
        } else {
            return $field_name;
        }
    }

    /* Add a 'initial' prefix for checking dynamic initial values */
    public function add_initial_prefix($field_name) {
        return sprintf('initial-%s', $this->add_prefix($field_name));
    }


//    protected function _html_output( normal_row, error_row, row_ender, help_text_html, errors_on_separate_row) {
//    }


    public function as_table() {
//        $top_errors = $this->non_field_errors();

        foreach( $this->fields as $name=>$field ) {
            $bf = new BoundField($this,$field,$name);

            $html_class_attr='';
            $errors = '';
            if( $bf->errors )
                $errors = join("\n",$bf->errors);
            $help_text='';

            $label='';
            if($bf->label) {
                // TODO: does label need escaping?
                $label=$bf->label;
                // TODO: sort out label suffix here
            }
            $out[]=sprintf( "<tr%s><th>%s</th><td>%s%s%s</td></tr>",
                $html_class_attr,
                $label,
                $errors,
                $bf->html(),
                $help_text );

        }
        return join("\n",$out);
    }

    public function as_ul() {
    }

    public function as_p() {
    }

    /*
        Returns an array of errors that aren't associated with a particular
        field -- i.e., from Form.clean(). Returns an empty array if there
        are none.
    */
    public function non_field_errors() {
        if(array_key_exists(NON_FIELD_ERRORS,$this->_errors)) {
            return $this->_errors[NON_FIELD_ERRORS];
        } else {
            return array();
        }
    }

    /*
        Returns the raw_value for a particular field name. This is just a
        convenient wrapper around widget.value_from_datadict.
    */
    protected function _raw_value($fieldname) {
        $field = $this->fields[$fieldname];
        $prefix = $this->add_prefix($fieldname);
        return $field->widget->value_from_datadict( $this->data, $this->files, $prefix);
    }

    /*Cleans all of self.data and populates self._errors and
       self.cleaned_data.
     */
    function full_clean() {
        $this->_errors = array();

        if(!$this->is_bound ) {
            return;
        }

        $this->cleaned_data = array();

        // If the form is permitted to be empty, and none of the form data has
        // changed from the initial data, short circuit any validation.

        // TODO: implement has_changed()
//        if( $this->empty_permitted and !$this->has_changed() )
//            return;

        $this->_clean_fields();
        $this->_clean_form();
        $this->_post_clean();
        if( $this->_errors ) {
            unset( $this->cleaned_data );
        }
    }


    function _clean_fields() {
        foreach($this->fields as $name=>$field) {
            // value_from_data() gets the data from the data arrays.
            // Each widget type knows how to retrieve its own data, because some
            // widgets split data over several HTML fields.
            $value = $field->widget->value_from_data($this->data, $this->files, $this->add_prefix($name));
            try {
                // TODO: special case for file handling
                $value = $field->clean($value);
                $this->cleaned_data[$name] = $value;
                /* TODO: could probably implement this in php:
                if hasattr(self, 'clean_%s' % name):
                    value = getattr(self, 'clean_%s' % name)()
                    self.cleaned_data[name] = value
                */
            } catch (ValidationError $e) {
                $this->_errors[$name] = $e->messages;
                if(array_key_exists($name,$this->cleaned_data)) {
                    unset($this->cleaned_data[$name]);
                }
            }
        }
    }

    function _clean_form() {
        try {
            $this->cleaned_data = $this->clean();
        } catch(ValidationError $e) {
            $this->_errors[NON_FIELD_ERRORS] = $e->messages;
        }
    }

    /* An internal hook for performing additional cleaning after form cleaning
        is complete. 
    */
    function _post_clean() {
    }


    /*
        Hook for doing any extra form-wide cleaning after Field.clean() been
        called on every field. Any ValidationError raised by this method will
        not be associated with a particular field; it will have a special-case
        association with the field named '__all__'.
     */
    function clean() {
        return $this->cleaned_data;
    }


    function has_changed() {
        return $this->changed_data ? TRUE:FALSE;
    }

    private function _get_changed_data() {
        if(is_null($this->changed_data)) {
            $this->_changed_data = array();
            /*
            # XXX: For now we're asking the individual widgets whether or not the
            # data has changed. It would probably be more efficient to hash the
            # initial data, store it in a hidden field, and compare a hash of the
            # submitted data, but we'd need a way to easily get the string value
            # for a given field. Right now, that logic is embedded in the render
            # method of each widget.
             */
            foreach($this->fields as $name=>$field) {
                $prefixed_name = $this->add_prefix($name);
                $data_value = $field->widget->value_from_data($this->data, $this->files, $prefixed_name);
                $initial_value=null;
                if(!$field->show_hidden_initial) {
                    if(array_key_exists($name,$this->initial)) {
                        $initial_value = $this->initial[$name];
                    } else {
                        $initial_value = $field->initial;
                    }
                } else {
                    $initial_prefixed_name = $this->add_initial_prefix($name);
                    $hidden_widget = $field->hidden_widget();
                    $initial_value = $hidden_widget->value_from_data(
                        $this->data, $this->files, $initial_prefixed_name);
                }
                if($field->widget->_has_changed($initial_value, $data_value)) {
                    $this->_changed_data[] = $name;
                }
            }
            return $this->_changed_data;
        }
    }
    // TODO:
    // media / _get_media()
    // is_multipart()
    //  hidden_fields()
    //  visible_fields()


}

// Converts 'first_name' to 'First name'
function pretty_name($name) {
    if(!$name)
        return '';
    $name = str_replace('_', ' ', $name);
    return ucwords($name);
}






// A Field plus data
class BoundField
{
    public function __construct( $form, $field, $name ) {
        $this->form = $form;
        $this->field = $field;
        $this->name = $name;
        $this->html_name = $form->add_prefix($name);
        $this->html_initial_name = $form->add_initial_prefix($name);
        $this->html_initial_id = $form->add_initial_prefix($this->auto_id);
        $this->label = $this->field->label ? $this->field->label : pretty_name($name);
        $this->help_text = $field->help_text ? $field->help_text : '';
    }

    public function __get($v) {
        if( $v=='errors' )
            return $this->_errors();
        if( $v=='data')
            return $this->_data();
        if( $v=='is_hidden')
            return $this->_is_hidden();
        if( $v=='auto_id')
            return $this->_auto_id();
        // TODO: should trigger an error here?
        return null;
    }

    // Renders this field as an HTML widget.
    public function html() {
        if( $this->field->show_hidden_initial )
            return $this->as_widget() + $this->as_hidden(TRUE);
        return $this->as_widget();
    }


    private function _errors() {
        if(array_key_exists($this->name,$this->form->errors))
            return $this->form->errors[$this->name];
    }

/*
        Renders the field by rendering the passed widget, adding any HTML
        attributes passed as attrs.  If no widget is specified, then the
        field's default widget will be used.
 */
    public function as_widget($widget=null, $attrs=null, $only_initial=FALSE) {
        // TODO: create default widget if widget==null...
        $widget = $this->field->widget;
        if( is_null($attrs) )
            $attrs = array();

        $auto_id = $this->auto_id;
        if( $auto_id and
            !array_key_exists('id', $attrs) and
            !array_key_exists('id', $widget->attrs) )
        {

            if(!$only_initial)
                $attrs['id'] = $auto_id;
            else
                $attrs['id'] = $this->html_initial_id;
        }

        $data = null;
        if(!$this->form->is_bound) {
            /* not bound - use initial value if set */
            $data = $this->field->initial;
            if( array_key_exists($this->name,$this->form->initial) ) {
                /* form has an initial value for this field */
                $data = $this->form->initial[$this->name];
            }
            /* TODO: python version allows a callable here:
            if callable(data):
                data = data()
            */
        } else {
            // TODO: special handling for FileField (see python version)
/* 
            if isinstance(self.field, FileField) and self.data is None:
                data = self.form.initial.get(self.name, self.field.initial)
            else:
                data = self.data
 */
            $data = $this->data;
        }

        $name = $this->html_name;
        if( $only_initial)
            $name = $this->html_initial_name;

        return $widget->render($name, $data, $attrs);
    }


/*
    def as_text(self, attrs=None, **kwargs):
        """
        Returns a string of HTML for representing this as an <input type="text">.
        """
        return self.as_widget(TextInput(), attrs, **kwargs)
*/


/*
    def as_textarea(self, attrs=None, **kwargs):
        "Returns a string of HTML for representing this as a <textarea>."
        return self.as_widget(Textarea(), attrs, **kwargs)
*/


    public function as_hidden( $attrs=null ) {
        // TODO
        throw Exception( "Not implemented" );
    }

    // Returns the data for this BoundField, or None if it wasn't given.
    private function _data() {
        return $this->field->widget->value_from_data($this->form->data, $this->form->files, $this->html_name );
    }


    /*
        Wraps the given contents in a <label>, if the field has an ID attribute.
        Does not HTML-escape the contents. If contents aren't given, uses the
        field's HTML-escaped label.

        If attrs are given, they're used as HTML attributes on the <label> tag.
    */
    public function label_tag($contents=null, $attrs=null) {
        $contents = $contents ? $contents : htmlspecialchars($this->label);
        $widget = $this->field->widget;
        if( array_key_exists('id',$widget->attrs) ) {
            $id = $widget->attr['id'];
        } else {
            $id = $this->auto_id;
        }
        if($id) {
            $contents = sprintf('<label for="%s"%s>%s</label>',
                $widget->id_for_label($id), flatatt($attrs), $contents);
        }
        return $contents;
    }



/*
    def css_classes(self, extra_classes=None):
        """
        Returns a string of space-separated CSS classes for this field.
        """
        if hasattr(extra_classes, 'split'):
            extra_classes = extra_classes.split()
        extra_classes = set(extra_classes or [])
        if self.errors and hasattr(self.form, 'error_css_class'):
            extra_classes.add(self.form.error_css_class)
        if self.field.required and hasattr(self.form, 'required_css_class'):
            extra_classes.add(self.form.required_css_class)
        return ' '.join(extra_classes)
 */

    /* Returns True if this BoundField's widget is hidden. */
    private function _is_hidden() {
        return $this->field->widget->is_hidden;
    }

    /*
        Calculates and returns the ID attribute for this BoundField, if the
        associated Form has specified auto_id. Returns an empty string otherwise.
     */
    private function _auto_id() {
        $auto_id = $this->form->auto_id;
        if( $auto_id && strstr('%s',$auto_id) ) {
            return sprintf( $auto_id, $this->html_name );
        } elseif( $auto_id ) {
            return $this->html_name;
        } else {
            return '';
        }
    }
}


/* in original django code, Form adds syntactic sugar to BaseForm.
 * We'll just leave it at this for now.
 */
class Form extends BaseForm
{
}

