<?php
/*****************************************************************************/
/** @class Modules
 *  @brief  This @b abstract @b class defines a parameter set for modules
 *
 *  This abstract class sets the parameters used in Admidio modules.
 *  The class gets a copy of the $_GET Array and validates the values 
 *  with Admidio function admFuncVariableIsValid();
 *  Values are set to default if no parameters are submitted.
 *  The class also defines a daterange and returns the daterange as array with English format and current System format.
 *  If no values are available the daterange is set by default: date_from = DATE_NOW; date_to = 9999-12-31 
 *  The class provides methods to return all single Variables and arrays or returns an Array with all setted parameters
 *  The returned array contains following settings:
 *  @par Return parameter array:
 *  @code
 *  array('headline'    => 'string',
 *        'id'          => 'integer',
 *        'mode'        => 'string',
 *        'view_mode'   => 'string',
 *        'date'        => 'string',
 *        'daterange'   =>  array(
 *                               [english] (date_from => 'string', date_to => 'string'),
 *                               [sytem] (date_from => 'string', date_to => 'string'))
 *                               );
 *  @endcode                               
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Author       : Thomas-RCV
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
abstract class Modules
{
    const HEADLINE = '';        ///< Constant for language parameter set in modul classes
    
    public    $arrParameter;    ///< Array with validated parameters 
    protected $headline;        ///< String with headline expression
    protected $id;              ///< ID as integer to choose record
    protected $date;            ///< String with date value
    protected $daterange;       ///< Array with date settings in English format and system format
    protected $mode;            ///< String with current mode ( Deafault: "Default" )
    protected $start;           ///< Integer for start element
    private   $properties;      ///< Array Clone of $_GET Array
    protected $validModes;      ///< Array with valid modes ( Deafault: "Default" )
    protected $viewMode;        ///< Array with valid view modes ( Deafault: "Default" )
    
    abstract public function getDataSet($startElement=0, $limit=NULL);
    abstract public function getDataSetCount();
    
    /** Constuctor that will create an object of a parameter set needed in modules to get the recordsets.
     *  Initialize parameters
     */
    public function __construct()
    {
        $this->arrParameters    = array();
        $this->date             = '';
        $this->daterange        = array();
        $this->headline         = '';
        $this->id               = 0;
        $this->mode             = 'Default';
        $this->properties       = $_GET;
        $this->start            = '';
        $this->validModes       = array('Default');
        $this->viewMode         = 'Default';
        
        // Set parameters
        $this->setDate();
        $this->setDaterange();
        $this->setHeadline();
        $this->setId();
        $this->setMode();
        $this->setStartElement();
        $this->setViewMode();
    }
    
    /**
     *  Return Date
     *  @return Returns the explicit date in English format
     */
    public function getDate()
    {
        return $this->date;
    }
    
    /**
     *  Return the daterange
     *  @return Returns daterange as array with English format and system format
     */
    public function getDaterange()
    {
        return $this->daterange;
    }
    
    /**
     *  Return Headline
     *  @return Returns headline as string
     */
    public function getHeadline()
    {
        return $this->headline;
    }
    
    /**
     *  Return ID
     *  @return Returns the ID of the record
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     *  Return mode
     *  @return Returns mode as string
     */
    public function getMode()
    {
        return $this->mode;
    }
    
    /**
     *  Return start element
     *  @return Returns Integer value for the start element
     */
    public function getStartElement()
    {
        return $this->start;
    }
    
    /**
     *  Return view mode
     *  @return Returns view mode as string
     */
    public function getViewMode()
    {
        return $this->viewMode;
    }
    
    /**
     *  Return parameter set as Array
     *  @return Returns an Array with all needed parameters as Key/Value pair 
     */
    public function getParameter()
    {
        // Set Array
        $this->arrParameter['date']         = $this->date;
        $this->arrParameter['daterange']    = $this->daterange;
        $this->arrParameter['headline']     = $this->headline;
        $this->arrParameter['id']           = $this->id;
        $this->arrParameter['mode']         = $this->mode;
        $this->arrParameter['startelement'] = $this->start;
        $this->arrParameter['view_mode']    = $this->viewMode;
        return $this->arrParameter;
    }
    
    /**
     *  Set date value and convert in English format if necessary
     */
    protected function setDate()
    {
        global $gPreferences;
        $date = '';
        
        // check optional user parameter and make secure. Otherwise set default value
        $date = admFuncVariableIsValid($this->properties, 'date', 'date', '', false);
        
        // Create date object and format date in English format 
        $objDate = new DateTimeExtended($date, 'Y-m-d', 'date');
        
        if($objDate->valid())
        {
            $this->date = substr($objDate->getDateTimeEnglish(), 0, 10);
        }
        else
        {
            // check if date has system format then convert it in English format
            $objDate = new DateTimeExtended($date, $gPreferences['system_date'], 'date');
            if($objDate->valid())
            {
                $this->date = substr($objDate->getDateTimeEnglish(), 0, 10);
            }
        }
    }
    
    /**
     *  Set daterange in an array with values for English format and system format
     *  @return Returns false if invald date format is submitted 
     */
    protected function setDaterange()
    {
        global $gPreferences;
        $start  = '';
        $end    = '';
        
        // check optional user parameter and make secure. Otherwise set default value
        $start = admFuncVariableIsValid($this->properties, 'date_from', 'date', DATE_NOW, false);
        
        // Create date object and format date_from in English format and sytem format and push to daterange array
        $objDate = new DateTimeExtended($start, 'Y-m-d', 'date');
        if($objDate->valid())
        {
            $this->daterange['english']['start_date'] = substr($objDate->getDateTimeEnglish(), 0, 10);
            $this->daterange['system']['start_date'] = $objDate->format($gPreferences['system_date']);
        }                                             
        else
        {
            // check if date_from  has system format
            $objDate = new DateTimeExtended($start, $gPreferences['system_date'], 'date');

            if($objDate->valid())
            {
                $this->daterange['english']['start_date'] = substr($objDate->getDateTimeEnglish(), 0, 10);
                $this->daterange['system']['start_date'] = $objDate->format($gPreferences['system_date']);
            }
            else
            {
                return false;
            }
        }

        // check optional user parameter and make secure. Otherwise set default value
        $end = admFuncVariableIsValid($this->properties, 'date_to', 'date', '9999-12-31', false);

        // Create date object and format date_to in English format and sytem format and push to daterange array
        $objDate = new DateTimeExtended($end, 'Y-m-d', 'date');
        if($objDate->valid())
        {
            $this->daterange['english']['end_date'] = substr($objDate->getDateTimeEnglish(), 0, 10);
            $this->daterange['system']['end_date'] = $objDate->format($gPreferences['system_date']);
        }
        else
        {
            // check if date_from  has system format
            $objDate = new DateTimeExtended($end, $gPreferences['system_date'], 'date');

            if($objDate->valid())
            {
                $this->daterange['english']['end_date'] = substr($objDate->getDateTimeEnglish(), 0, 10);
                $this->daterange['system']['end_date'] = $objDate->format($gPreferences['system_date']);
            }
            else
            {
                return false;
            }
        }
        
    }
    
    /**
     *  Set headline
     * 
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as headline in the modules. Otherwise the headline is set from language file
     *  
     */
    protected function setHeadline()
    {   
        // check optional user parameter and make secure. Otherwise set default value
        $this->headline = admFuncVariableIsValid($this->properties, 'headline', 'string', HEADLINE);   
    }
    
    /**
     *  Set ID
     */
    protected function setId()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->id = admFuncVariableIsValid($this->properties, 'id', 'numeric', 0);
    }
    
    /**
     *  Set mode 
     * 
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as mode in the modules. Otherwise mode is set to default
     */
    protected function setMode()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->mode = admFuncVariableIsValid($this->properties, 'mode', 'string', $this->mode, false, $this->validModes);
    }
    
    /**
     *  Set startelement
     *
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as startelement in the modules. Otherwise startelement is set to 0
     */
    protected function setStartElement()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->start = admFuncVariableIsValid($this->properties, 'start', 'numeric', 0);
    }
    
    /**
     *  Set viewmode
     * 
     *  @par If user string is set in $_GET Array the string is validated by Admidio function and set as viewmode in the modules. Otherwise mode is set to default
     */
    protected function setViewMode()
    {
        // check optional user parameter and make secure. Otherwise set default value
        $this->viewMode = admFuncVariableIsValid($this->properties, 'viewMode', 'string', $this->viewMode);
    }
}
?>