<?php 
/*
* Create and edit navigation menu 
*/
class MCPMenuFormMenu extends MCPModule {
	
	private
	
	/*
	* Menu data access layer 
	*/
	$_objDAOMenu
	
	/*
	* Form validator 
	*/
	,$_objValidator
	
	/*
	* Current menu 
	*/
	,$_arrMenu
	
	/*
	* Form post data 
	*/
	,$_arrFrmPost
	
	/*
	* Form values 
	*/
	,$_arrFrmValues
	
	/*
	* Form errors 
	*/
	,$_arrFrmErrors;
	
	public function __construct(MCP $objMCP,MCPModule $objParentModule=null,$arrConfig=null) {
		parent::__construct($objMCP,$objParentModule,$arrConfig);
		$this->_init();
	}
	
	private function _init() {
		// Get Menu DAO
		$this->_objDAOMenu = $this->_objMCP->getInstance('Component.Menu.DAO.DAOMenu',array($this->_objMCP));
		
		// Get form validator
		$this->_objValidator = $this->_objMCP->getInstance('App.Lib.Validation.Validator',array());
		
		// reset form errors and values
		$this->_arrFrmValues = array();
		$this->_arrFrmErrors = array();
		
		// fetch form post data
		$this->_arrFrmPost = $this->_objMCP->getPost($this->_getFrmName());
		
		// Add custom validation routines to validator
		$this->_addCustomValidationRules();
	}
	
	/*
	* Add custom validation callbacks to validator
	* 
	*/
	private function _addCustomValidationRules() {
		
		$mcp = $this->_objMCP;
		$dao = $this->_objDAOMenu;
		$menu =& $this->_arrMenu;
		
		$this->_objValidator->addRule('menu_system_name',function($value,$label) use (&$menu,$mcp,$dao) {
			
			/*
			* Check system name conforms to standard convention 
			*/
			if(!preg_match('/^[a-z0-9_]*?$/',$value)) {
				return "$label may only contain numbers, underscores and lower alphabetic characters.";
			}
			
			/*
			* Build filter to see if navigation menu already exists 
			*/
			$strFilter = sprintf(
				"m.deleted = 0 AND m.sites_id = %s AND m.system_name = '%s' %s"
				
				,$mcp->escapeString( $mcp->getSitesId() )
				,$mcp->escapeString( $value )
				
				// edit edge case
				,$menu !== null ? " AND m.system_name <> '{$mcp->escapeString($menu['system_name'])}'": ''
			);
			
			/*
			* Check to see if another menu exists with given name within site
			*/
			if(array_pop($dao->listAllMenus('m.menus_id',$strFilter)) !== null) {
				return "$label $value already exists please use another name";
			}
			
			return '';
		});
		
	}
	
	/*
	* Handle form data 
	*/
	private function _handleForm() {
		
		/*
		* Set form values 
		*/
		$this->_setFrmValues();
		
		/*
		* validate form values 
		*/
		if($this->_arrFrmPost !== null) {
			$this->_arrFrmErrors = $this->_objValidator->validate($this->_getFrmConfig(),$this->_arrFrmValues);
		}
		
		/*
		* Save menu data to database 
		*/
		if($this->_arrFrmPost !== null && empty($this->_arrFrmErrors)) {
			$this->_frmSave();
		}
		
	}
	
	/*
	* Set form values 
	*/
	private function _setFrmValues() {
		if($this->_arrFrmPost !== null) {
			$this->_setFrmSaved();
		} else if($this->_getMenu() !== null) {
			$this->_setFrmEdit();
		} else {
			$this->_setFrmCreate();
		}
	}
	
	/*
	* Set form values from submited post 
	*/
	private function _setFrmSaved() {
		
		/*
		* Set values from post array 
		*/
		foreach($this->_getFrmFields() as $strField) {
			switch($strField) {
				
				default:
					$this->_arrFrmValues[$strField] = isset($this->_arrFrmPost[$strField])?$this->_arrFrmPost[$strField]:'';
			}
		}
		
	}
	
	/*
	* Set form values from current menu 
	*/
	private function _setFrmEdit() {
		
		/*
		* Get current menu 
		*/
		$arrMenu = $this->_getMenu();
		
		/*
		* Set values from current menu 
		*/
		foreach($this->_getFrmFields() as $strField) {
			$this->_arrFrmValues[$strField] = $arrMenu[$strField];
		}
		
	}
	
	/*
	* Set new menu form defaults 
	*/
	private function _setFrmCreate() {
		
		/*
		* Set empty and/or default values for creating new menu
		*/
		foreach($this->_getFrmFields() as $strField) {
			switch($strField) {
				
				case 'display_title':
					$this->_arrFrmValues[$strField] = 1;
					break;
				
				default:
					$this->_arrFrmValues[$strField] = ''; 
			}
		}
		
	}
	
	/*
	* Save form menu data to database 
	*/
	private function _frmSave() {
		
		/*
		* Copy values array 
		*/
		$arrValues = $this->_arrFrmValues;
		
		/*
		* Get current menu 
		*/
		$arrMenu = $this->_getMenu();
		
		if($arrMenu !== null) {
			$arrValues['menus_id'] = $arrMenu['menus_id'];
		} else {
			$arrValues['sites_id'] = $this->_objMCP->getSitesId();
			$arrValues['users_id'] = $this->_objMCP->getUsersId();
		}
		
		/*
		* Save menu data 
		*/
		try {
			
			$this->_objDAOMenu->saveMenu($arrValues);
			
			/*
			* Fire update event using this as the target
			*/
			$this->_objMCP->fire($this,'MENU_UPDATE');
		
			/*
			* Add success message 
			*/
			$this->_objMCP->addSystemStatusMessage( $this->_getSaveSuccessMessage() );
			
		} catch(MCPDAOException $e) {
			
			$this->_objMCP->addSystemErrorMessage(
				$this->_getSaveErrorMessage()
				,$e->getMessage()
			);
			
			return false;
			
		}
		
		return true;
		
	}
	
	/*
	* Message to be shown to user upon sucessful save of menu link
	* 
	* @return str message
	*/
	protected function _getSaveSuccessMessage() {
		return 'Menu '.($this->_getMenu() !== null?'Updated':'Created' ).'!';
	}

	/*
	* Message to be shown to user when error occurs saving of menu link
	* 
	* @return str message
	*/
	protected function _getSaveErrorMessage() {
		return 'An internal issue has prevented the menu from being '.($this->_getMenu() !== null?'updated':'created' );
	}
	
	/*
	* Get form definition
	* 
	* @return array form definition
	*/
	private function _getFrmConfig() {
		/*
		* Gte form configuration from MCP 
		*/
		return $this->_objMCP->getFrmConfig($this->getPkg());
	}
	
	/*
	* Get forms name
	* 
	* @return str form name
	*/
	private function _getFrmName() {
		return 'frmMenu';
	}
	
	/*
	* Get form fields
	* 
	* @return array fields
	*/
	private function _getFrmFields() {
		return array_keys( $this->_getFrmConfig() );
	}
	
	/*
	* Get current menu data
	* 
	* @return array current menu data
	*/
	private function _getMenu() {
		return $this->_arrMenu;
	}
	
	/*
	* Set current menu data
	* 
	* @param array current menu
	*/
	private function _setMenu($arrMenu) {
		$this->_arrMenu = $arrMenu;
	}
	
	public function execute($arrArgs) {
		
		/*
		* Extract menu to edits id if exists 
		*/
		$intMenuId = !empty($arrArgs) && is_numeric($arrArgs[0])?array_shift($arrArgs):null;
		
		/*
		* Attempt to fetch requested menu data and set for edit 
		*/
		if($intMenuId !== null) {
			/*
			* Get menu data form database 
			*/
			$arrMenu = $this->_objDAOMenu->fetchMenuById($intMenuId);
			
			/*
			* Set menu as menu to edit 
			*/
			if($arrMenu !== null) {
				$this->_setMenu($arrMenu);
			}
			
		}
		
		/*
		* Check permissions 
		* Can user add/ edit menu?
		*/
		$perm = $this->_objMCP->getPermission(($intMenuId===null?MCP::ADD:MCP::EDIT),'Menu',$intMenuId);
		if(!$perm['allow']) {
			throw new MCPPermissionException($perm);
		}
		
		/*
		* Handle form data 
		*/
		$this->_handleForm();
		
		/*
		* Assign template data 
		*/
		$this->_arrTemplateData['action'] = $this->getBasePath();
		$this->_arrTemplateData['method'] = 'POST';
		$this->_arrTemplateData['name'] = $this->_getFrmName();
		$this->_arrTemplateData['config'] = $this->_getFrmConfig();
		$this->_arrTemplateData['values'] = $this->_arrFrmValues;
		$this->_arrTemplateData['errors'] = $this->_arrFrmErrors;
		$this->_arrTemplateData['legend'] = 'Menu';
		
		return 'Menu/Menu.php';
	}
	
	/*
	* Override base path to append navigation menu reference for edit
	* 
	* @return str base path
	*/
	public function getBasePath() {
		
		$strPath = parent::getBasePath();
		$arrMenu = $this->_getMenu();
		
		if($arrMenu !== null) {
			$strPath.= "/{$arrMenu['menus_id']}";
		}
		
		return $strPath;
	}
	
}
?>