# drongo-forms


Drongo forms is a PHP library for handling HTML forms. It helps with
rendering forms and with validating form data. 
It's a port of
[Django forms](https://docs.djangoproject.com/en/dev/topics/forms/).


## Installation

TODO: figure out the idomatic PHP way of packaging stuff!
for now, I usually just symlink it into somewhere in my php path.

    $ ln -s /blahblahblah/drongo-forms/lib drongo-forms


## Philosopy

The aim is to produce a nice, clean, easy-to-use forms library for PHP.
The fact that its use will be familiar to django-forms users is just a nice
by-product.

Drongo-forms is more or less a line-by-line translation of the original
python code. Source filenames, class and variable names have been kept
similar and even the ordering of functions has been retained to aid comparison.
The PHP diverges from the original python wherever it makes sense. If you have
to jump through hoops to use some feature of drongo-forms, then something needs
fixing.


## Reference - Forms

Use array access to add fields to a form.
Use array access to retrieve fields for reading, or for use in templates.


form members:
is_valid()
as_table()
as_ul()
as_p()
non_field_errors()
has_changed()

errors - an array of all the errors on the form
changed_data -
media -

## Reference - Fields


(see the original [django reference on fields](https://docs.djangoproject.com/en/dev/ref/forms/fields/))

Options are passed in via the `$opts` array parameter in field
constructors. All fields have the following:

* `required` - (default: `TRUE`) validation will fail unless this field is filled out
* `widget` - override the default widget for this field (can be a string containing the name of the widget type, or a widget instance)
* `label` - (default: derive a human-friendly name from the fields name)
* `initial` - initial value for the field
* `help_text` -
* `error_messages` - array of error messages to override defaults
* `show_hidden_initial` -
* `validators` - an array of validators to apply to this field

### BooleanField
* Default widget: `CheckboxInput`
* Empty value: `FALSE`
* Normalizes to: `TRUE` or `FALSE`
* Validates that the value is `TRUE` (e.g. the check box is checked) if the field has `required=TRUE`.
* Error message keys: `required`

Note: Since all Field subclasses have `'required'=>TRUE` by default, the
validation condition here is important. If you want to include a boolean
in your form that can be either True or False (e.g. a checked or unchecked
checkbox), you must remember to pass in `'required'=>FALSE` when creating
the `BooleanField`.


### CharField

* Default widget: `TextInput`
* Empty value: '' (an empty string)
* Normalizes to: A Unicode object.
* Validates `max_length` or `min_length`, if they are provided. Otherwise, all inputs are valid.
* Error message keys: `required`, `max_length`, `min_length`

Has two optional arguments for validation:

* `max_length`
* `min_length`

If provided, these arguments ensure that the string is at most or at least the given length.

### IntegerField
### FloatField

### DateField

### FileField
### RegexField
### EmailField


### URLField

### ChoiceField
### MultipleChoiceField



## Hints

By default `CharField` uses a single line text input, but you can
use a `Textarea` widget for multi-line text, eg:

        $form->$fields['address'] = new CharField( array('widget'=>'TextArea') );


if you want special css classes for required fields or fields with errors, add
`error_css_class` and/or `required_css_class` to your form, eg:

        $this->error_css_class = 'fld-error';
        $this->required_css_class = 'fld-required';

### Differences from django forms

* no fancy metaclass setup - just add fields directly to form classes
* media
    - need to use full paths (lack of global settings)
    - set form_media member on form for form global media
* TODO: more!


### TODO

* define a class for error messages, with helpers to make them easier to use in templates
