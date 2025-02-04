<?php
// abstract base class
$this->import('App.Resource.Permission.ChildLevelPermission');

/*
* Vocabulary term permissions data access layer
*/
class MCPPermissionTerm extends MCPChildLevelPermission {
	
	protected function _getBaseTable() {
		return 'MCP_TERMS';
	}
	
	protected function _getParentTable() {
		return 'MCP_VOCABULARY';
	}
	
	protected function _getPrimaryKey() {
		return 'terms_id';
	}
	
	protected function _getParentPrimaryKey() {
		return 'vocabulary_id';
	}
	
	protected function _getItemType() {
		return 'MCP_TERMS';
	}
	
	protected function _getParentItemType() {
		return 'MCP_VOCABULARY';
	}
	
	protected function _getCreator() {
		return 'creators_id';
	}
	
	protected function _getParentCreator() {
		return 'creators_id';
	}
	
}     
?>