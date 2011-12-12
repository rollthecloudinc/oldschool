<?php
/*
* All unresolved modules are passed though here
*/
class MCPRouteRouter extends MCPModule {
	
	private
	
	/*
	* Router and Menu data access layer 
	*/
	$_objDAORoute
        ,$_objDAOMenu
	
	,$_strComponent
	,$_arrArgs
	
	/*
	* Navigation links associated with request 
	*/
	,$_arrLink
	,$_boolEdit
	,$_arrExternalArgs
	
	,$_strContent
	,$_strTpl
	
	/*
	* content switch 
	*/
	,$_strContentType = null;
	
	public function __construct(MCP $objMCP,MCPModule $objParentModule=null,$arrConfig=null) {
		parent::__construct($objMCP,$objParentModule,$arrConfig);
		$this->_init();
	}
	
	private function _init() {
		// Fetch Route DAO
		$this->_objDAORoute = $this->_objMCP->getInstance('Component.Route.DAO.DAORoute',array($this->_objMCP));
		
		// Fetch Menu DAO
		$this->_objDAOMenu = $this->_objMCP->getInstance('Component.Menu.DAO.DAOMenu',array($this->_objMCP));
                
		// Set defaults
		$this->_strContent = '';
		$this->_strTpl = 'PageNotFound';
	}
	
	public function execute($arrArgs) {
	
		$strModule = empty($arrArgs)?null:array_shift($arrArgs);
		
		if($strModule !== null) {
			if(strcmp($strModule,'Index') == 0) {
				$this->_executeGlobalIndex();
			} else if(strcmp($strModule,'Master') == 0) {
				$this->_executeGlobalMaster();
			} else if(strcmp($strModule,'') == 0) {
				$this->_executeSiteIndex();
			} else if(strcmp($strModule,'Component') == 0) {
				$this->_executeComponent($arrArgs);
			} else if(strcmp($strModule,'PlatForm') == 0) {
				$this->_executePlatForm($arrArgs);				
			} else if(strcmp($strModule,'Admin') == 0) {
				$this->_executeAdmin($arrArgs);
			} else if(strcmp($strModule,'Permission Denied') == 0) {
				$this->_executePermissionDenied($arrArgs);
			} else {
				$this->_executeDynamic($strModule,$arrArgs);
			}
		}
		
		$this->_arrTemplateData['ROUTE_CONTENT'] = $this->_strContent;
		$this->_arrTemplateData['nav_link'] = $this->_arrLink;
		$this->_arrTemplateData['edit_link'] = $this->_boolEdit;
		$this->_arrTemplateData['link_path'] = $this->_getLinkPath(!$this->_boolEdit);
		$this->_arrTemplateData['content_type'] = $this->_strContentType;
		$this->_arrTemplateData['display_edit_link'] = false;
		
		/*
		* Only show edit link for pages current user is able to edit 
		*/
		if( $this->_arrLink ) {
			$perm = $this->_objMCP->getPermission(MCP::EDIT,'MenuLink',$this->_arrLink['menu_links_id']);
			$this->_arrTemplateData['display_edit_link'] = $perm['allow'];
		}
		
		return "Router/{$this->_strTpl}.php";
		
	}
	
	private function _executePermissionDenied($arrArgs) {
		$this->_arrTemplateData['message'] = !empty($arrArgs)?array_shift($arrArgs):'Permission Denied';
		$this->_strTpl = 'PermissionDenied';
	}
	
	private function _executeGlobalIndex() {		
		$this->_strContent = $this->_objMCP->executeComponent('Component.Util.Module.Index',array());
		$this->_strTpl = 'Redirect';
	}
	
	private function _executeSiteIndex() {
		$this->_strContent = $this->_objMCP->executeModule('Site.*.Module.Index',array(),'Site.*.Template',null,null,1);
		$this->_strTpl = 'Redirect';
	}
	
	private function _executeGlobalMaster() {		
		$this->_strContent = $this->_objMCP->executeComponent('Component.Util.Module.Master',array());
		$this->_strTpl = 'Redirect';
	}
	
	private function _executeAdmin($arrArgs) {
		
		/*
		* In most cases the only people able to access the main admin
		* area should be developers. Actions are controlled using the permission
		* management system on an entity/module by module basis. Being able
		* to access the admin area only gives a user the ability to see the admin
		* area. This has no impact on the ability to actually carry out tasks. Therefore,
		* modules and associated actions can be exposed to people via nesting without
		* giving them direct access to the actual admin area. This is the prefered way to develop
		* an application considering the domain can be sculpted in a way that specific to the
		* users business needs rather than needing to understand the the generic, components
		* used to carry out business tasks. 
		*/
		$perm = $this->_objMCP->getPermission(MCP::READ,'Route','Admin/*');
		if( !$perm['allow'] ) {
			throw new MCPPermissionException($perm);
		}
		
		/*
		* The admin module is able to be changed or extended by doing so and changing the config
		* admin module path to the location of the new admin module.
		*/
		$this->_strContent = $this->_objMCP->executeComponent($this->_objMCP->getConfigValue('site_admin_module'),$arrArgs,null,array($this));
		$this->_strTpl = 'Redirect';
	}
	
	/*
	* Provides global access to components
	* 
	* @param array request args
	*/
	private function _executeComponent($arrArgs) {

		/*
		* Protected. Developers should be the only ones who are able to access
		* components directly. 
		*/
		$perm = $this->_objMCP->getPermission(MCP::READ,'Route','Component/*');
		/*if( !$perm['allow'] ) { // this is driving me crazy
			throw new MCPPermissionException($perm);
		}*/
		
		/*
		* Get requested component name 
		*/
		$this->_strComponent = !empty($arrArgs)?array_shift($arrArgs):'';
		$this->_arrArgs = $arrArgs;
		
		/*
		* Execute component and assign content to template
		*/
		$this->_strContent = $this->_objMCP->executeComponent("Component.{$this->_strComponent}",$this->_arrArgs,'',array($this));
		
		$this->_strTpl = 'Redirect';
	}
	
	/*
	* Provides global access to platforms
	* 
	* @param array request args
	*/
	private function _executePlatForm($arrArgs) {
		
		/*
		* Protected. Developers should be the only ones who are able to access
		* platform modules directly. 
		*/
		$perm = $this->_objMCP->getPermission(MCP::READ,'Route','PlatForm/*');
		if( !$perm['allow'] ) {
			throw new MCPPermissionException($perm);
		}
		
		/*
		* Get requested component name 
		*/
		$this->_strComponent = !empty($arrArgs)?array_shift($arrArgs):'';
		$this->_arrArgs = $arrArgs;
		
		/*
		* Execute component and assign content to template
		*/
		$this->_strContent = $this->_objMCP->executeComponent("PlatForm.{$this->_strComponent}",$this->_arrArgs,'',array($this));
		
		$this->_strTpl = 'Redirect';
	}
	
	private function _executeDynamic($strModule,$arrArgs) {
		
		/*
		* Attempt to locate route and map to menu item
		*/
		$intId = $this->_objDAORoute->fetchRoute($strModule,$this->_objMCP->getSitesId());
                
                
                /*
                * If route was not found go no further 
                */
                if($intId === null) {
                    return;
                }
                
                /*
                * Otherwise locate link 
                */
                $arrLink = $this->_objDAOMenu->fetchLinkById($intId);
                unset($intId);
		
		/*
		* Dynamic links will map to a module.
		*/
		if($arrLink === null || strcmp($arrLink['target'],'MODULE') !== 0) {
			return;
		}
		
		// assign link associated with request
		$this->_arrLink = $arrLink;
		
		// check for special edit keyword
		$this->_boolEdit = !empty($arrArgs) && strcmp('Link-Edit',$arrArgs[0]) == 0 && array_shift($arrArgs)?true:false; 
		
		// set external arguments
		$this->_arrExternalArgs = $arrArgs;
		
		// on edit match internal redirect to link editor
		if($this->_boolEdit === true) {
			
			$this->_strContent = $this->_objMCP->executeComponent(
				'Component.Menu.Module.Form.Link'
				,array($arrLink['menu_links_id'])
				,null
				,array($this)
				,array(array($this,'onLinkUpdate'),'LINK_UPDATE')
			);
			$this->_strTpl = 'Redirect';
			return;
		}
							
		$arrRouteArgs = array(
			$arrLink['mod_path']
			,empty($arrLink['mod_args'])?$arrArgs:array_merge($arrLink['mod_args'],$arrArgs)
		);
				
		if(!empty($arrLink['mod_tpl'])) {
			$arrRouteArgs[] = $arrLink['mod_tpl'];
		} else {
			$arrRouteArgs[] = null;
		}
		
		$arrRouteArgs[] = array(
			$this
			,!empty($arrLink['mod_cfg'])?$arrLink['mod_cfg']:null
		);
                
                // $this->_objMCP->debug($arrRouteArgs);
                
		
		// fow now make this magical - in the future perhaps add boolean to navigation_links table to differentiate component
		if(strpos($arrLink['mod_path'],'Component.') === 0 || strpos($arrLink['mod_path'],'PlatForm.') === 0) {
			$this->_strContent = call_user_func_array(array($this->_objMCP,'executeComponent'),$arrRouteArgs);	
		} else {
			$this->_strContent = call_user_func_array(array($this->_objMCP,'executeModule'),$arrRouteArgs);	
		}
		
		$this->_strTpl = 'Redirect';
	}
	
	public function getBasePath() {
		
		/*
		* Get parent base path 
		*/
		$strBasePath = parent::getBasePath();
		
		if($this->_boolEdit === true) {
			$strBasePath = "$strBasePath/Link-Edit";
			
			if(!empty($this->_arrExternalArgs)) {
				$strBasePath.= '/'.implode('/',$this->_arrExternalArgs);
			}
			
		}
		
		if($this->_strComponent === null) {
			return $strBasePath;
		} else {
			return "$strBasePath/{$this->_strComponent}";
		}
		
	}
	
	/*
	* Creates edit and back path for a navigation link
	* 
	* @param bool edit
	* @return str back or edit path for navigation link
	*/
	private function _getLinkPath($boolEdit=false) {
		if($this->_arrLink === null) return null;
		
		$strLinkPath = parent::getBasePath();
		
		if($boolEdit === true) {
			$strLinkPath.= "/Link-Edit";
		}
		
		if(!empty($this->_arrExternalArgs)) {
			$strLinkPath.= '/'.implode('/',$this->_arrExternalArgs);
		}
		
		return $strLinkPath;
		
	}
	
	/*
	* Called when is updated
	*
	* @param obj event target
	*/
	public function onLinkUpdate($objTarget) {
		/*
		* update link data
		*/
		$this->_arrLink = $this->_objDAOMenu->fetchLinkById($this->_arrLink['menu_links_id']);
		
		/*
		* Reset the request module
		*/
		$this->_objMCP->setModule($this->_arrLink['path']);
	}
	
}
?>