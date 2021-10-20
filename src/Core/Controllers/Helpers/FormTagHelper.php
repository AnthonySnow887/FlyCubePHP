<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 8/29/21
 * Time: 5:45 PM
 */

namespace FlyCubePHP\Core\Controllers\Helpers;

include_once __DIR__.'/../../Protection/RequestForgeryProtection.php';
include_once 'BaseControllerHelper.php';

use \FlyCubePHP\Core\Protection\RequestForgeryProtection as RequestForgeryProtection;

class FormTagHelper extends BaseControllerHelper
{
    function __construct() {
        $this->appendSafeFunction("form_with");
        $this->appendSafeFunction("label");
        $this->appendSafeFunction("raw");
        $this->appendSafeFunction("button_tag");
        $this->appendSafeFunction("submit");
        $this->appendSafeFunction("file_field");
        $this->appendSafeFunction("hidden_field");
        $this->appendSafeFunction("check_box");
        $this->appendSafeFunction("color_field");
        $this->appendSafeFunction("date_field");
        $this->appendSafeFunction("datetime_field");
        $this->appendSafeFunction("datetime_local_field");
        $this->appendSafeFunction("email_field");
        $this->appendSafeFunction("month_field");
        $this->appendSafeFunction("number_field");
        $this->appendSafeFunction("password_field");
        $this->appendSafeFunction("telephone_field");
        $this->appendSafeFunction("phone_field");
        $this->appendSafeFunction("radio_button");
        $this->appendSafeFunction("range_field");
        $this->appendSafeFunction("search_field");
        $this->appendSafeFunction("text_area");
        $this->appendSafeFunction("text_field");
        $this->appendSafeFunction("time_field");
        $this->appendSafeFunction("url_field");
        $this->appendSafeFunction("week_field");
    }

    /**
     * Add form tag
     * @param array $options
     * @return string
     * @throws \Exception
     *
     *  ==== Options
     *
     * - url                - Set form action url
     * - method             - Set form method (get/post/put/patch/delete)
     * - authenticity_token - Enable/Disable authenticity token (true/false)
     * - encrypt_type       - Set form encrypt type (default: empty)
     * - html_body          - Set form html body
     *
     * ==== Examples in Twig notations
     *
     * - Example 1:
     * {% set form_html_body %}
     * <label for="my-input">Test text:</label>
     * <input type="text" id="my-input" name="my-input-text" />
     * <input type="submit" name="commit" value="Commit data">
     * {% endset %}
     * {% set form_url = make_valid_url('test') %}
     * {{ form_with({'html_body': form_html_body, 'url': form_url, 'encrypt_type': 'multipart/form-data'}) }}
     *
     * Result:
     *     <form accept-charset="UTF-8" enctype="multipart/form-data" action="/my_project/test" method="post">
     *     <input name="authenticity_token" type="hidden" value="et2tJwUsPjBz05i6lzlrHPHcR1CctzZcpI3Gj/F+YyQ7I+cIO+LqMiK3RVVh1olxjiA+C9rNdy4WrdZGFhdiUw==" />
     *     <label for="my-input">Test text:</label>
     *     <input type="text" id="my-input" name="my-input-text" />
     *     <input type="submit" name="commit" value="Commit data" />
     *     </form>
     *
     * - Example 2:
     * {% set form_html_body %}
     * {{ label('Test text:', {'for': 'my-input'}) }}
     * {{ text_field({'id': 'my-input', 'name': 'my-input-text'}) }}
     * {{ submit() }}
     * {% endset %}
     * {% set form_url = make_valid_url('test') %}
     * {{ form_with({'html_body': form_html_body, 'url': form_url, 'encrypt_type': 'multipart/form-data'}) }}
     *
     * Result:
     *     <form accept-charset="UTF-8" enctype="multipart/form-data" action="/my_project/test" method="post">
     *     <input name="authenticity_token" type="hidden" value="et2tJwUsPjBz05i6lzlrHPHcR1CctzZcpI3Gj/F+YyQ7I+cIO+LqMiK3RVVh1olxjiA+C9rNdy4WrdZGFhdiUw==" />
     *     <label for="my-input" >Test text:</label>
     *     <input type="text" id="my-input" name="my-input-text" />
     *     <input type="submit" name="commit" value="Commit data" />
     *     </form>
     *
     * - Example 3 - disabled authenticity token:
     * {{ form_with({'html_body': form_html_body, 'url': form_url, 'encrypt_type': 'multipart/form-data', 'authenticity_token': false}) }}
     *
     * Result:
     *     <form accept-charset="UTF-8" enctype="multipart/form-data" action="/my_project/test" method="post">
     *     <label for="my-input">Test text:</label>
     *     <input type="text" id="my-input" name="my-input-text" />
     *     <input type="submit" name="commit" value="Commit data" />
     *     </form>
     */
    public function form_with(array $options = array()): string {
        $action = "/";
        if (isset($options['url']))
            $action = $options['url'];

        $method = "post";
        if (isset($options['method']))
            $method = strtolower(trim($options['method']));

        $methodTypeTag = "";
        if (strcmp($method, "put") === 0
            || strcmp($method, "patch") === 0
            || strcmp($method, "delete") === 0) {
            $methodTypeTag = "<input name=\"_method\" type=\"hidden\" value=\"$method\" />";
            $method = "post";
        }

        $authenticityToken = true;
        if (isset($options['authenticity_token'])
            && $options['authenticity_token'] === false)
            $authenticityToken = false;

        $encType = "";
        if (isset($options['encrypt_type'])
            && !empty($options['encrypt_type']))
            $encType = "enctype=\"" . trim($options['encrypt_type']) . "\"";

        $html = "<form accept-charset=\"UTF-8\" $encType action=\"$action\" method=\"$method\">";
        if (!empty($methodTypeTag))
            $html .= "\r\n" . $methodTypeTag;
        if ($authenticityToken === true
            && RequestForgeryProtection::instance()->isProtectFromForgery()) {
            $token = RequestForgeryProtection::instance()->formAuthenticityToken();
            $html .= "\r\n<input name=\"authenticity_token\" type=\"hidden\" value=\"$token\" />";
        }
        if (isset($options['html_body'])
            && !empty($options['html_body']))
            $html .= "\r\n" . trim($options['html_body']);

        $html .= "\r\n</form>";
        return $html;
    }

    /**
     * Add label tag
     * @param string $text
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id     - Set label id
     * - for    - Set label for
     * - class  - Set label class
     *
     * ==== Examples in Twig notations
     *
     * {{ label('Test text:', {'for': 'my-input'}) }}
     * {{ text_field({'id': 'my-input', 'name': 'my-input-text'}) }}
     *
     * Result:
     *     <label for="my-input" >Test text:</label>
     *     <input type="text" id="my-input" name="my-input-text" />
     */
    public function label(string $text, array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $for = "";
        if (isset($options['for']) && !empty($options['for']))
            $for = "for=\"" . $options['for'] . "\"";

        $class = "";
        if (isset($options['class']) && !empty($options['class']))
            $class = "class=\"" . $options['class'] . "\"";

        return "<label $id $for $class>$text</label>";
    }

    /**
     * Add raw html data
     * @param string $val
     * @return string
     *
     * ==== Examples in Twig notations
     *
     * raw('<label for="my-input" >Test text:</label>')
     *
     * Result:
     *     <label for="my-input" >Test text:</label>
     */
    public function raw(string $val): string {
        return strval($val);
    }

    /**
     * Add input button tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name (default: commit)
     * - class      - Set input class
     * - value      - Set input value (default: Commit data)
     * - type       - Set input type (button/reset/submit) (default: button)
     * - disabled   - Set input disabled (default: false)
     *
     * ==== Examples in Twig notations
     *
     * {{ button_tag({'id': 'my-button-id', 'name': 'my-button', 'value': 'some button value', 'type': 'button'}) }}
     *
     * or
     *
     * {{ button_tag({'id': 'my-button-id', 'name': 'my-button', 'value': 'some button value'}) }}
     *
     * Result:
     *     <input type="button" id="my-button-id" name="my-button" value="some button value" />
     */
    public function button_tag(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "name=\"commit\"";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "value=\"Commit data\"";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $class = "";
        if (isset($options['class']) && !empty($options['class']))
            $class = "class=\"" . $options['class'] . "\"";

        $type = "type=\"button\"";
        if (isset($options['type']) && !empty($options['type']))
            $type = "type=\"" . $options['type'] . "\"";

        $disabled = "";
        if (isset($options['disabled']) && $options['disabled'] === true)
            $disabled = "disabled=\"disabled\"";

        return "<input $type $id $name $class $value $disabled />";
    }

    /**
     * Add input submit tag button
     * @param array $options
     * @return string
     *
     *  NOTE: this overload function -> see button_tag function
     *
     * ==== Examples in Twig notations
     *
     * {{ submit() }}
     *
     * Result:
     *     <input type="submit" name="commit" value="Commit data" />
     */
    public function submit(array $options = []): string {
        $options['type'] = "submit";
        return $this->button_tag($options);
    }

    /**
     * Add input file tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name (default: commit)
     * - class      - Set input class
     * - accept     - Set to one or multiple mime-types, the user will be suggested a filter when choosing a file.
     * - multiple   - Set to true, *in most updated browsers* the user will be allowed to select multiple files (default: false)
     *
     * ==== Examples in Twig notations
     *
     * file_field({'id': 'files', 'multiple': true})
     * * => <input type="file" id="files" name="commit[]" multiple="multiple" />
     *
     * file_field({'id': 'file', 'accept': 'image/png,image/gif,image/jpeg'})
     * * => <input type="file" id="file" name="commit" accept="image/png,image/gif,image/jpeg" />
     */
    public function file_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $multiple = "";
        $multipleName = "";
        if (isset($options['multiple']) && $options['multiple'] === true) {
            $multiple = "multiple=\"multiple\"";
            $multipleName = "[]";
        }

        $name = "name=\"commit$multipleName\"";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $class = "";
        if (isset($options['class']) && !empty($options['class']))
            $class = "class=\"" . $options['class'] . "\"";

        $accept = "";
        if (isset($options['accept']) && !empty($options['accept']))
            $accept = "accept=\"" . $options['accept'] . "\"";

        return "<input type=\"file\" $id $name $class $accept $multiple />";
    }

    /**
     * Add input hidden tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - value      - Set input value
     *
     * ==== Examples in Twig notations
     *
     * hidden_field({'id': 'my-hidden-id', 'name': 'my-hidden-obj', 'value': 'some hidden value'})
     * * => <input type="hidden" id="my-hidden-id" name="my-hidden-obj" value="some hidden value" />
     */
    public function hidden_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        return "<input type=\"hidden\" $id $name $value />";
    }

    /**
     * Add checkbox tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id                 - Set input id
     * - name               - Set input name
     * - class              - Set input class
     * - checked_value      - Set checked value (default: 1)
     * - unchecked_value    - Set unchecked value (default: 0)
     *
     * ==== Examples in Twig notations
     *
     * {{ check_box({'name': 'chk_test'}) }}
     * * => <input type="hidden" name="chk_test" value="0" />
     *      <input type="checkbox" checked="checked"  name="chk_test"  checked="checked" value="1" />
     *
     * {{ check_box({'name': 'chk_test_2', 'checked_value': 'yes', 'unchecked_value': 'no'}) }}
     * * => <input type="hidden" name="chk_test_2" value="no" />
     *      <input type="checkbox" checked="checked"  name="chk_test_2"  checked="checked" value="yes" />
     */
    public function check_box(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $checkedValue = "1";
        if (isset($options['checked_value']) && !empty($options['checked_value']))
            $checkedValue = strval($options['checked_value']);

        $uncheckedValue = "0";
        if (isset($options['unchecked_value']) && !empty($options['unchecked_value']))
            $uncheckedValue = strval($options['unchecked_value']);

        $value = "value=\"$checkedValue\"";
        $hidenValue = "value=\"$uncheckedValue\"";
        $checked = "checked=\"checked\"";

        $class = "";
        if (isset($options['class']) && !empty($options['class']))
            $class = "class=\"" . $options['class'] . "\"";

        return <<<EOT
<input type="hidden" $name $hidenValue />
<input type="checkbox" $checked $id $name $class $checked $value />
EOT;
    }

    /**
     * Add color field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id     - Set input id
     * - name   - Set input name
     * - value  - Set input value (format: #rrggbb)
     *
     * ==== Examples in Twig notations
     *
     * color_field({'id': 'my-color-id', 'name': 'my-color', 'value': '#4c4c4c'})
     * * => <input type="color" id="my-color-id" name="my-color" value="#4c4c4c" />
     */
    public function color_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        return "<input type=\"color\" $id $name $value />";
    }

    /**
     * Add date field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id     - Set input id
     * - name   - Set input name
     * - value  - Set input value (format: yyyy-MM-dd)
     * - min    - Set input min value (format: yyyy-MM-dd)
     * - max    - Set input max value (format: yyyy-MM-dd)
     *
     * ==== Examples in Twig notations
     *
     * date_field({'id': 'my-date-id', 'name': 'my-date', 'value': '2021-10-18'})
     * * => <input type="date" id="my-date-id" name="my-date" value="2021-10-18" />
     */
    public function date_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $min = "";
        if (isset($options['min']) && !empty($options['min']))
            $min = "min=\"" . $options['min'] . "\"";

        $max = "";
        if (isset($options['max']) && !empty($options['max']))
            $max = "max=\"" . $options['max'] . "\"";

        return "<input type=\"date\" $id $name $value $min $max />";
    }

    /**
     * Add date-time field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id     - Set input id
     * - name   - Set input name
     * - value  - Set input value (fromat: yyyy-MM-ddThh:mm)
     * - min    - Set input min value (fromat: yyyy-MM-ddThh:mm)
     * - max    - Set input max value (fromat: yyyy-MM-ddThh:mm)
     *
     * ==== Examples in Twig notations
     *
     * datetime_field({'id': 'my-datetime-id', 'name': 'my-datetime', 'value': '2021-10-18T15:10'})
     * * => <input type="datetime-local" id="my-datetime-id" name="my-datetime" value="2021-10-18T15:10" />
     */
    public function datetime_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $min = "";
        if (isset($options['min']) && !empty($options['min']))
            $min = "min=\"" . $options['min'] . "\"";

        $max = "";
        if (isset($options['max']) && !empty($options['max']))
            $max = "max=\"" . $options['max'] . "\"";

        return "<input type=\"datetime-local\" $id $name $value $min $max />";
    }

    /**
     * Add date-time-local field tag
     * @param array $options
     * @return string
     *
     * NOTE: this is alias for datetime_field function.
     *
     * ==== Examples in Twig notations
     *
     * datetime_local_field({'id': 'my-datetime-id', 'name': 'my-datetime', 'value': '2021-10-18T15:10'})
     * * => <input type="datetime-local" id="my-datetime-id" name="my-datetime" value="2021-10-18T15:10" />
     */
    public function datetime_local_field(array $options = []): string {
        return $this->datetime_field($options);
    }

    /**
     * Add email field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - pattern    - Set input regular expression the input's contents must match in order to be valid
     * - size       - Set input number indicating how many characters wide the input field should be
     * - maxlength  - Set input maximum number of characters the input should accept
     * - minlength  - Set input minimum number of characters long the input can be and still be considered valid
     * - required   - Set input is required (default: false)
     *
     * ==== Examples in Twig notations
     *
     * email_field({'id': 'my-email-id', 'name': 'my-email', 'pattern': '.+@my\.com', 'size': 20, 'required': true})
     * * => <input type="email" id="my-email-id" name="my-email" pattern=".+@my.com" size="20" required />
     */
    public function email_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $pattern = "";
        if (isset($options['pattern']) && !empty($options['pattern']))
            $pattern = "pattern=\"" . $options['pattern'] . "\"";

        $size = "";
        if (isset($options['size']) && !empty($options['size']))
            $size = "size=\"" . $options['size'] . "\"";

        $maxLength = "";
        if (isset($options['maxlength']) && !empty($options['maxlength']))
            $maxLength = "maxlength=\"" . $options['maxlength'] . "\"";

        $minLength = "";
        if (isset($options['minlength']) && !empty($options['minlength']))
            $minLength = "minlength=\"" . $options['minlength'] . "\"";

        $required = "";
        if (isset($options['required']) && $options['required'] === true)
            $required = "required";

        return "<input type=\"email\" $id $name $pattern $size $maxLength $minLength $required />";
    }

    /**
     * Add month field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id     - Set input id
     * - name   - Set input name
     * - value  - Set input value (format: yyyy-MM)
     * - min    - Set input min value (format: yyyy-MM)
     * - max    - Set input max value (format: yyyy-MM)
     *
     * ==== Examples in Twig notations
     *
     * month_field({'id': 'my-month-id', 'name': 'my-month', 'value': '2021-10'})
     * * => <input type="month" id="my-month-id" name="my-month" value="2021-10" />
     */
    public function month_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $min = "";
        if (isset($options['min']) && !empty($options['min']))
            $min = "min=\"" . $options['min'] . "\"";

        $max = "";
        if (isset($options['max']) && !empty($options['max']))
            $max = "max=\"" . $options['max'] . "\"";

        return "<input type=\"month\" $id $name $value $min $max />";
    }

    /**
     * Add number field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id     - Set input id
     * - name   - Set input name
     * - value  - Set input value
     * - min    - Set input min value
     * - max    - Set input max value
     * - step   - Set input stepping interval to use when using up and down arrows to adjust the value, as well as for validation
     *
     * ==== Examples in Twig notations
     *
     * number_field({'id': 'my-number-id', 'name': 'my-number', 'value': 2021, 'min': 2000, 'max': 2500, 'step': 5})
     * * => <input type="number" id="my-number-id" name="my-number" value="2021" min="2000" max="2500" step="5" />
     */
    public function number_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $min = "";
        if (isset($options['min']) && !empty($options['min']))
            $min = "min=\"" . $options['min'] . "\"";

        $max = "";
        if (isset($options['max']) && !empty($options['max']))
            $max = "max=\"" . $options['max'] . "\"";

        $step = "";
        if (isset($options['step']) && !empty($options['step']))
            $step = "step=\"" . $options['step'] . "\"";

        return "<input type=\"number\" $id $name $value $min $max $step />";
    }

    /**
     * Add input password field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - class      - Set input class
     * - size       - Set input size
     * - value      - Set input value
     * - onchange   - Set input onchange handler
     *
     * ==== Examples in Twig notations
     *
     * {% set jquery_content %}
     * if ($('#my-password-id').val().length > 30) { alert('Your password needs to be shorter!'); }
     * {% endset %}
     * {{ password_field({'id': 'my-password-id', 'name': 'my-password', 'size': 10, 'onchange': jquery_content}) }}
     *
     * Result:
     *     <input type="password" id="my-password-id" name="my-password"  size="10"  onchange="if ($('#my-password-id').val().length > 30) { alert('Your password needs to be shorter!'); }" />
     */
    public function password_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $class = "";
        if (isset($options['class']) && !empty($options['class']))
            $class = "class=\"" . $options['class'] . "\"";

        $size = "";
        if (isset($options['size']) && !empty($options['size']))
            $size = "size=\"" . $options['size'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $onChange = "";
        if (isset($options['onchange']) && !empty($options['onchange']))
            $onChange = "onchange=\"" . $options['onchange'] . "\"";

        return "<input type=\"password\" $id $name $class $size $value $onChange />";
    }

    /**
     * Add telephone field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - pattern    - Set input regular expression the input's contents must match in order to be valid
     * - size       - Set input number indicating how many characters wide the input field should be
     * - maxlength  - Set input maximum number of characters the input should accept
     * - minlength  - Set input minimum number of characters long the input can be and still be considered valid
     * - required   - Set input is required (default: false)
     *
     * ==== Examples in Twig notations
     *
     * telephone_field({'id': 'my-tel-id', 'name': 'my-tel', 'pattern': '[0-9]{3}-[0-9]{3}-[0-9]{4}', 'size': 10, 'required': true})
     * * => <input type="tel" id="my-tel-id" name="my-tel" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" size="10" required />
     */
    public function telephone_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $pattern = "";
        if (isset($options['pattern']) && !empty($options['pattern']))
            $pattern = "pattern=\"" . $options['pattern'] . "\"";

        $size = "";
        if (isset($options['size']) && !empty($options['size']))
            $size = "size=\"" . $options['size'] . "\"";

        $maxLength = "";
        if (isset($options['maxlength']) && !empty($options['maxlength']))
            $maxLength = "maxlength=\"" . $options['maxlength'] . "\"";

        $minLength = "";
        if (isset($options['minlength']) && !empty($options['minlength']))
            $minLength = "minlength=\"" . $options['minlength'] . "\"";

        $required = "";
        if (isset($options['required']) && $options['required'] === true)
            $required = "required";

        return "<input type=\"tel\" $id $name $pattern $size $maxLength $minLength $required />";
    }

    /**
     * Add phone field tag
     * @param array $options
     * @return string
     *
     * NOTE: this is alias for telephone_field function.
     *
     * ==== Examples in Twig notations
     *
     * phone_field({'id': 'my-tel-id', 'name': 'my-tel', 'pattern': '[0-9]{3}-[0-9]{3}-[0-9]{4}', 'size': 10, 'required': true})
     * * => <input type="tel" id="my-tel-id" name="my-tel" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" size="10" required />
     */
    public function phone_field(array $options = []): string {
        return $this->telephone_field($options);
    }

    /**
     * Add radio button tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - value      - Set input value
     * - checked    - Set input is checked (default: false)
     *
     * ==== Examples in Twig notations
     *
     * radio_button({'id': 'post_category_rails', 'name': 'post[category]', 'value': 'rails', 'checked': true})
     * radio_button({'id': 'post_category_rails', 'name': 'post[category]', 'value': 'java'})
     * * => <input type="radio" id="post_category_rails" name="post[category]" value="rails" checked="checked" />
     *      <input type="radio" id="post_category_java" name="post[category]" value="java" />
     *
     */
    public function radio_button(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $checked = "";
        if (isset($options['checked']) && $options['checked'] === true)
            $checked = "checked=\"checked\"";

        return "<input type=\"radio\" $id $name $value $checked />";
    }

    /**
     * Add range field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id     - Set input id
     * - name   - Set input name
     * - value  - Set input value
     * - min    - Set input min value
     * - max    - Set input max value
     * - step   - Set input stepping interval to use when using up and down arrows to adjust the value, as well as for validation
     *
     * ==== Examples in Twig notations
     *
     * range_field({'id': 'my-range-id', 'name': 'my-range', 'value': 5, 'min': 0, 'max': 10, 'step': 1})
     * * => <input type="range" id="my-range-id" name="my-range" value="5" min="0" max="10" step="1" />
     */
    public function range_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $min = "";
        if (isset($options['min'])) {
            if (is_string($options['min']) && !empty($options['min']))
                $min = "min=\"" . $options['min'] . "\"";
            else if (is_numeric($options['min']))
                $min = "min=\"" . $options['min'] . "\"";
        }

        $max = "";
        if (isset($options['max'])) {
            if (is_string($options['max']) && !empty($options['max']))
                $max = "max=\"" . $options['max'] . "\"";
            else if (is_numeric($options['max']))
                $max = "max=\"" . $options['max'] . "\"";
        }

        $step = "";
        if (isset($options['step']) && !empty($options['step']))
            $step = "step=\"" . $options['step'] . "\"";

        return "<input type=\"range\" $id $name $value $min $max $step />";
    }

    /**
     * Add search field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - pattern    - Set input regular expression the input's contents must match in order to be valid
     * - size       - Set input number indicating how many characters wide the input field should be
     * - maxlength  - Set input maximum number of characters the input should accept
     * - minlength  - Set input minimum number of characters long the input can be and still be considered valid
     *
     * ==== Examples in Twig notations
     *
     * search_field({'id': 'my-search-id', 'name': 'my-search', 'pattern': '[A-z]{2}[0-9]{4}', 'size': 6})
     * * => <input type="search" id="my-search-id" name="my-search" pattern="[A-z]{2}[0-9]{4}" size="6" />
     */
    public function search_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $pattern = "";
        if (isset($options['pattern']) && !empty($options['pattern']))
            $pattern = "pattern=\"" . $options['pattern'] . "\"";

        $size = "";
        if (isset($options['size']) && !empty($options['size']))
            $size = "size=\"" . $options['size'] . "\"";

        $maxLength = "";
        if (isset($options['maxlength']) && !empty($options['maxlength']))
            $maxLength = "maxlength=\"" . $options['maxlength'] . "\"";

        $minLength = "";
        if (isset($options['minlength']) && !empty($options['minlength']))
            $minLength = "minlength=\"" . $options['minlength'] . "\"";

        return "<input type=\"search\" $id $name $pattern $size $maxLength $minLength />";
    }

    /**
     * Add text area tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - cols       - The visible width of the text control, in average character widths.
     *                If it is specified, it must be a positive integer. If it is not specified, the default value is 20.
     * - rows       - The number of visible text lines for the control.
     * - class      - Set input class
     * - text_body  - Set input text body (auto escape enabled)
     * - disabled   - Set input disabled (default: false)
     *
     * ==== Examples in Twig notations
     *
     * text_area({'id': 'my-text-area-id', 'name': 'my-text-area', 'rows': 10, 'text_body': 'Some text...'})
     * * => <textarea id="my-text-area-id" rows="10" name="my-text-area">
     *      Some text...
     *      </textarea>
     */
    public function text_area(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $cols = "";
        if (isset($options['cols']) && !empty($options['cols']))
            $cols = "cols=\"" . $options['cols'] . "\"";

        $rows = "";
        if (isset($options['rows']) && !empty($options['rows']))
            $rows = "rows=\"" . $options['rows'] . "\"";

        $class = "";
        if (isset($options['class']) && !empty($options['class']))
            $class = "class=\"" . $options['class'] . "\"";

        $disabled = "";
        if (isset($options['disabled']) && $options['disabled'] === true)
            $disabled = "disabled=\"disabled\"";

        $textBody = "";
        if (isset($options['text_body']))
            $textBody = htmlentities($options['text_body']);

        return <<<EOT
<textarea $id $cols $rows $name $class $disabled>
$textBody
</textarea>
EOT;
    }

    /**
     * Add input text field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - class      - Set input class
     * - size       - Set input size
     * - value      - Set input value
     * - maxlength  - Set input max length
     * - minlength  - Set input min length
     * - onchange   - Set input onchange handler
     *
     * ==== Examples in Twig notations
     *
     * {% set jquery_content %}
     * if ($('#my-text-id').val().length > 30) { alert('Your text needs to be shorter!'); }
     * {% endset %}
     * {{ text_field({'id': 'my-text-id', 'name': 'my-text', 'size': 10, 'value': '123-qwe-456-rty', 'onchange': jquery_content}) }}
     *
     * Result:
     *     <input type="text" id="my-text-id" name="my-text" size="10" value="123-qwe-456-rty" onchange="if ($('#my-text-id').val().length > 30) { alert('Your text needs to be shorter!'); }" />
     */
    public function text_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $class = "";
        if (isset($options['class']) && !empty($options['class']))
            $class = "class=\"" . $options['class'] . "\"";

        $size = "";
        if (isset($options['size']) && !empty($options['size']))
            $size = "size=\"" . $options['size'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $maxLength = "";
        if (isset($options['maxlength']) && !empty($options['maxlength']))
            $maxLength = "maxlength=\"" . $options['maxlength'] . "\"";

        $minLength = "";
        if (isset($options['minlength']) && !empty($options['minlength']))
            $minLength = "minlength=\"" . $options['minlength'] . "\"";

        $onChange = "";
        if (isset($options['onchange']) && !empty($options['onchange']))
            $onChange = "onchange=\"" . $options['onchange'] . "\"";

        return "<input type=\"text\" $id $name $class $size $value $minLength $maxLength $onChange />";
    }

    /**
     * Add time field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - value      - Set input value (format: hh:mm)
     * - min        - Set input min value (format: hh:mm)
     * - max        - Set input max value (format: hh:mm)
     * - step       - Set input stepping interval to use when using up and down arrows to adjust the value, as well as for validation
     * - required   - Set input is required (default: false)
     *
     * ==== Examples in Twig notations
     *
     * time_field({'id': 'my-time-id', 'name': 'my-time', 'value': '17:16', 'min': '13:00', 'max': '18:00', 'required': true})
     * * => <input type="time" id="my-time-id" name="my-time" value="17:16" min="13:00" max="18:00" required />
     */
    public function time_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $min = "";
        if (isset($options['min']) && !empty($options['min']))
            $min = "min=\"" . $options['min'] . "\"";

        $max = "";
        if (isset($options['max']) && !empty($options['max']))
            $max = "max=\"" . $options['max'] . "\"";

        $step = "";
        if (isset($options['step']) && !empty($options['step']))
            $step = "step=\"" . $options['step'] . "\"";

        $required = "";
        if (isset($options['required']) && $options['required'] === true)
            $required = "required";

        return "<input type=\"time\" $id $name $value $min $max $step $required />";
    }

    /**
     * Add input url field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id             - Set input id
     * - name           - Set input name
     * - value          - Set input value
     * - pattern        - Set input regular expression the input's contents must match in order to be valid
     * - placeholder    - Set input exemplar value to display in the input field whenever it is empty
     * - size           - Set input size
     * - maxlength      - Set input max length
     * - minlength      - Set input min length
     * - required       - Set input is required (default: false)
     *
     * ==== Examples in Twig notations
     *
     * url_field({'id': 'my-url-id', 'name': 'my-url', 'placeholder': 'https://example.com', 'pattern': 'https://.*', 'size': 30, 'required': true})
     * * => <input type="url" id="my-url-id" name="my-url" size="30" placeholder="https://example.com" pattern="https://.*" required />
     */
    public function url_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $pattern = "";
        if (isset($options['pattern']) && !empty($options['pattern']))
            $pattern = "pattern=\"" . $options['pattern'] . "\"";

        $placeholder = "";
        if (isset($options['placeholder']) && !empty($options['placeholder']))
            $placeholder = "placeholder=\"" . $options['placeholder'] . "\"";

        $size = "";
        if (isset($options['size']) && !empty($options['size']))
            $size = "size=\"" . $options['size'] . "\"";

        $maxLength = "";
        if (isset($options['maxlength']) && !empty($options['maxlength']))
            $maxLength = "maxlength=\"" . $options['maxlength'] . "\"";

        $minLength = "";
        if (isset($options['minlength']) && !empty($options['minlength']))
            $minLength = "minlength=\"" . $options['minlength'] . "\"";

        $required = "";
        if (isset($options['required']) && $options['required'] === true)
            $required = "required";

        return "<input type=\"url\" $id $name $minLength $maxLength $size $placeholder $pattern $required $value />";
    }

    /**
     * Add input week field tag
     * @param array $options
     * @return string
     *
     *  ==== Options
     *
     * - id         - Set input id
     * - name       - Set input name
     * - value      - Set input value (format: yyyy-Wnn, where nn is a number with a leading zero)
     * - min        - Set input min value (format: yyyy-Wnn, where nn is a number with a leading zero)
     * - max        - Set input max value (format: yyyy-Wnn, where nn is a number with a leading zero)
     * - step       - Set input stepping interval to use when using up and down arrows to adjust the value, as well as for validation
     * - required   - Set input is required (default: false)
     *
     * ==== Examples in Twig notations
     *
     * week_field({'id': 'my-week-id', 'name': 'my-week', 'value': '2021-W42'})
     * * => <input type="week" id="my-week-id" name="my-week" value="2021-W42" />
     */
    public function week_field(array $options = []): string {
        $id = "";
        if (isset($options['id']) && !empty($options['id']))
            $id = "id=\"" . $options['id'] . "\"";

        $name = "";
        if (isset($options['name']) && !empty($options['name']))
            $name = "name=\"" . $options['name'] . "\"";

        $value = "";
        if (isset($options['value']) && !empty($options['value']))
            $value = "value=\"" . $options['value'] . "\"";

        $max = "";
        if (isset($options['max']) && !empty($options['max']))
            $max = "max=\"" . $options['max'] . "\"";

        $min = "";
        if (isset($options['min']) && !empty($options['min']))
            $min = "min=\"" . $options['min'] . "\"";

        $step = "";
        if (isset($options['step']) && !empty($options['step']))
            $step = "step=\"" . $options['step'] . "\"";

        $required = "";
        if (isset($options['required']) && $options['required'] === true)
            $required = "required";

        return "<input type=\"week\" $id $name $min $max $step $required $value />";
    }
}