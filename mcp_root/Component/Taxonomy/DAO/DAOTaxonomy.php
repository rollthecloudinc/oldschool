<?php 
/*
* Taxonomy data access layer 
*/
class MCPDAOTaxonomy extends MCPDAO {
	
	/*
	* List vocabulary
	* 
	* @param str columns
	* @param str where clause
	* @param str sort
	* @param str limit
	* @return array vocabulary
	*/
	public function listVocabulary($strSelect='v.*',$strFilter=null,$strSort=null,$strLimit=null) {
		
		/*
		* Build query 
		*/
		$strSQL = sprintf(
			'SELECT
			      %s
			      %s
			      ,v.vocabulary_id tmp_vocabs_id
			   FROM
			      MCP_VOCABULARY v
			     %s
			     %s
			     %s'
			,$strLimit === null?'':'SQL_CALC_FOUND_ROWS'
			,$strSelect
			,$strFilter === null?'':"WHERE $strFilter"
			,$strSort === null?'':"ORDER BY $strSort"
			,$strLimit === null?'':"LIMIT $strLimit"
		);
		
		/*
		* Query db 
		*/
		$arrVocabulary = $this->_objMCP->query($strSQL);
		
		/*
		* Add in dynamic fields - Internal columns used to add dynamic field data after removed
		*/
		foreach($arrVocabulary as &$arrVocab) {
			$arrVocab = $this->_objMCP->addFields($arrVocab,$arrVocab['tmp_vocabs_id'],'MCP_VOCABULARY');
			unset($arrVocab['tmp_vocabs_id']);
		}
		
		/*
		* When without limit just return result set 
		*/
		if($strLimit === null) {
			return $arrVocabulary;
		}
		
		/*
		* Return bundle of data and number of total rows 
		*/
		return array(
			$arrVocabulary
			,array_pop(array_pop($this->_objMCP->query('SELECT FOUND_ROWS()')))
		);
		
	}
	
	/*
	* List terms
	* 
	* @param str columns
	* @param str where clause
	* @param str sort
	* @param str limit
	* @return array terms
	*/
	public function listTerms($strSelect='t.*',$strFilter=null,$strSort=null,$strLimit=null) {
		
		/*
		* Build query 
		*/
		$strSQL = sprintf(
			'SELECT
			      %s
			      %s
			      ,t.terms_id tmp_terms_id
			      ,t.parent_type tmp_parent_type
			      ,t.parent_id tmp_parent_id
			   FROM
			      MCP_TERMS t
			     %s
			     %s
			     %s'
			,$strLimit === null?'':'SQL_CALC_FOUND_ROWS'
			,$strSelect
			,$strFilter === null?'':"WHERE $strFilter"
			,$strSort === null?'':"ORDER BY $strSort"
			,$strLimit === null?'':"LIMIT $strLimit"
		);
		
		/*
		* Query db 
		*/
		$arrTerms = $this->_objMCP->query($strSQL);
		
		/*
		* Add in dynamic fields - Internal columns used to add dynamic field data after removed
		*/
		foreach($arrTerms as &$arrTerm) {
			
			if(strcmp('vocabulary',$arrTerm['tmp_parent_type']) === 0) {
				$entity_id = $arrTerm['tmp_parent_id'];
			} else {
				$vocab = $this->fetchTermsVocabulary($arrTerm['tmp_parent_id']);
				$entity_id = $vocab['vocabulary_id'];
			}
			
			$arrTerm = $this->_objMCP->addFields($arrTerm,$arrTerm['tmp_terms_id'],'MCP_VOCABULARY',$entity_id);
			unset($arrTerm['tmp_terms_id'],$arrTerm['tmp_parent_id'],$arrTerm['tmp_parent_type']);
		}
		
		/*
		* When without limit just return result set 
		*/
		if($strLimit === null) {
			return $arrTerms;
		}
		
		/*
		* Return bundle of data and number of total rows 
		*/
		return array(
			$arrTerms
			,array_pop(array_pop($this->_objMCP->query('SELECT FOUND_ROWS()')))
		);
		
	}
	
	/*
	* Fetch vocabulary by id
	* 
	* @param int vocabularies id
	* @param str select columns
	* @return array vocabulary data
	*/
	public function fetchVocabularyById($intVocabularyId,$strSelect='v.*') {
		$arrVocab = array_pop($this->_objMCP->query(sprintf(
			'SELECT
			      %s
			   FROM
			      MCP_VOCABULARY v
			  WHERE
			      v.vocabulary_id = %s'
			,$strSelect
			,$this->_objMCP->escapeString($intVocabularyId)
		)));
		
		// decorate node with dynamic field values
		$arrVocab = $this->_objMCP->addFields($arrVocab,$intVocabularyId,'MCP_VOCABULARY');
		
		return $arrVocab;
		
	}
	
	/*
	* Fetch term by id
	* 
	* @param int terms id
	* @param str select columns
	* @return array term data
	*/
	public function fetchTermById($intTermsId,$strSelect='t.*') {
		$arrTerm = array_pop($this->_objMCP->query(sprintf(
			'SELECT
			      %s
			      ,parent_id tmp_parent_id
			      ,parent_type tmp_parent_type
			   FROM
			      MCP_TERMS t
			  WHERE
			      t.terms_id = %s'
			,$strSelect
			,$this->_objMCP->escapeString($intTermsId)
		)));
		
		// dynamic field vocab resolution
		if(strcmp('vocabulary',$arrTerm['tmp_parent_type']) === 0) {
			$entity_id = $arrTerm['tmp_parent_id'];
		} else {
			$vocab = $this->fetchTermsVocabulary($arrTerm['tmp_parent_id']);
			$entity_id = $vocab['vocabulary_id'];
		}
		
		// decorate node with dynamic field values
		$arrTerm = $this->_objMCP->addFields($arrTerm,$intTermsId,'MCP_VOCABULARY',$entity_id);
		
		return $arrTerm;
		
		
	}
	
	/*
	* Fetch all terms recursive
	* 
	* @param int parent id
	* @param str parent type [vocabulary or term]
	* @param bool recursive
	* @param array option set for selecting specific columns, adding filters or changing default sort order
	* @return array terms
	*/
	public function fetchTerms($intParentId,$strParentType='vocabulary',$boolR=true,$arrOptions=null) {
		
		/*
		* Build SQL 
		*/
		$strSQL = sprintf(
			"SELECT
			      %s
			   FROM
			      MCP_TERMS t
			  WHERE
			      t.parent_type = '%s'
			    AND
			      t.parent_id = %s
			      %s
			      %s"
			,$arrOptions !== null && isset($arrOptions['select'])?$arrOptions['select']:'t.*'
			,$this->_objMCP->escapeString($strParentType)
			,$this->_objMCP->escapeString($intParentId)
			,$arrOptions !== null && isset($arrOptions['filter'])?"AND {$arrOptions['filter']}":''
			,$arrOptions !== null && isset($arrOptions['sort'])?"ORDER BY {$arrOptions['sort']}":''
		);
		
		/*
		* Fetch terms 
		*/
		$arrTerms = $this->_objMCP->query($strSQL);
		
		/*
		* Recure 
		*/
		if($boolR === true) {
			foreach($arrTerms as &$arrTerm) {
				$children = $arrOptions !== null && isset($arrOptions['children'])?$arrOptions['children']:'terms';
				$arrTerm[$children] = $this->fetchTerms($arrTerm['terms_id'],'term',$boolR,$arrOptions);
			}
		}
		
		return $arrTerms;	
		
	}
	
	/*
	* Get the vocabulary a term belongs to
	* 
	* @param int terms id
	* @return array vocabularies data
	*/
	public function fetchTermsVocabulary($intTermsId,$runner=0,$echo=false) {
		
		$strSQL = sprintf(
			'SELECT
			      t.terms_id
			      ,t.parent_id
			      ,t.parent_type
			   FROM
			      MCP_TERMS t
			  WHERE
			      t.terms_id = %s'
			,$this->_objMCP->escapeString($intTermsId)
		);
		
		$arrRow = array_pop($this->_objMCP->query($strSQL));
		
		if(strcmp($arrRow['parent_type'],'vocabulary') != 0) {
			return $this->fetchTermsVocabulary($arrRow['parent_id'],($runner+1),$echo);
		}
		
		return $this->fetchVocabularyById($arrRow['parent_id']);
		
	}
	
	/*
	* Get all terms children (every descendent) 
	* 
	* Good to use to build a filter for the entire term
	* taking into consideration child items at every depth.
	* 
	* @param int terms id
	* @return array child terms
	*/
	public function getAllSubTerms($intTermsId) {
		
		/*
		* Get terms hierarchy
		*/
		$arrTerms = $this->fetchTerms($intTermsId,'term',true,array(
			// properly ommits items that have been soft deleted
			'filter'=>'t.deleted = 0'
		));
		
		/*
		* Recursive function used to flatten hierarchy
		*/
		$func = create_function(
			'$term,$func'
			,'if(!isset($term[\'terms\']) || empty($term[\'terms\'])) {
				return array();
			}
			
			$children = array();
			
			foreach($term[\'terms\'] as $child) {
				$children[] = $child;
				$children = array_merge($children,call_user_func($func,$child,$func));
			}
			
			return $children;'
		);
		
		/*
		* Flatten hierarchy 
		*/
		return call_user_func($func,array('terms'=>$arrTerms),$func);
		
	}
	
	/*
	* Insert or update vocabulary
	*/
	public function saveVocabulary($arrVocabulary) {		
		
		/*
		* Get fields native to table
		*/
		$schema = $this->_objMCP->query('DESCRIBE MCP_VOCABULARY');
		
		$native = array();
		foreach($schema as $column) {
			$native[] = $column['Field'];
		}
		
		/*
		* Siphon dynamic fields
		*/
		$dynamic = array();
		
		foreach(array_keys($arrVocabulary) as $field) {
			if(!in_array($field,$native)) {
				$dynamic[$field] = $arrVocabulary[$field];
				unset($arrVocabulary[$field]);
			}
		}
		
		$intId = $this->_save(
			$arrVocabulary
			,'MCP_VOCABULARY'
			,'vocabulary_id'
			,array('system_name','human_name','pkg','description')
			,'created_on_timestamp'	
			,null
			
			// Special argument to ignore casting empty string to NULL
			,array('pkg')
		);	
		
		/*
		* Save dynamic fields 
		*/
		$this->_objMCP->saveFieldValues($dynamic,(isset($arrVocabulary['vocabulary_id'])?$arrVocabulary['vocabulary_id']:$intId),'MCP_VOCABULARY');
		
		return $intId;
		
	}
	
	/*
	* Insert or update term
	*/
	public function saveTerm($arrTerm) {	
		
		/*
		* Get fields native to table
		*/
		$schema = $this->_objMCP->query('DESCRIBE MCP_TERMS');
		
		$native = array();
		foreach($schema as $column) {
			$native[] = $column['Field'];
		}
		
		/*
		* Siphon dynamic fields
		*/
		$dynamic = array();
		
		foreach(array_keys($arrTerm) as $field) {
			if(!in_array($field,$native)) {
				$dynamic[$field] = $arrTerm[$field];
				unset($arrTerm[$field]);
			}
		}
		
		$intId = $this->_save(
			$arrTerm
			,'MCP_TERMS'
			,'terms_id'
			,array('system_name','human_name','description','parent_type')
			,'created_on_timestamp'
		);	
		
		/*
		* Resolve the vocabulary 
		*/
		$pk = isset($arrTerm['terms_id'])?$arrTerm['terms_id']:$intId;
		$vocab = $this->fetchTermsVocabulary($pk);
		$entity_id = $vocab['vocabulary_id'];

		/*
		* Save dynamic fields 
		*/
		$this->_objMCP->saveFieldValues($dynamic,$pk,'MCP_VOCABULARY',$entity_id);
		
		return $intId;
		
	}
	
	/*
	* Delete a vocabulary
	* 
	* @param mix single integer value or array of integer values ( MCP_VOCABULARY primary key )
	*/
	public function deleteVocabulary($mixVocabularyId) {
		
		$strSQL = sprintf(
			'UPDATE
			      MCP_VOCABULARY
			    SET
			      MCP_VOCABULARY.deleted = NULL
			  WHERE
			      MCP_VOCABULARY.vocabulary_id IN (%s)'
			      
			,is_array($mixVocabularyId) ? $this->_objMCP->escapeString(implode(',',$mixVocabularyId)) : $this->_objMCP->escapeString($mixVocabularyId)
		);
		
		// echo "<p>$strSQL</p>";
		return $this->_objMCP->query($strSQL);
		
	}
	
	/*
	* Delete a term and all its children
	* 
	* NOTE: This code is pretty much swipped from the removeLink method
	* inside the navigation DAO. It is pretty much the same process considering
	* the tree structure is the takes on a same form and similar dpenedent methods exist.
	* 
	* @param int terms id
	*/
	public function deleteTerm($intTermsId) {
		
		/*
		* Get terms data 
		*/
		$arrTarget = $this->fetchTermById($intTermsId);
		
		/*
		* Get all child terms 
		*/
		$arrTerms = $this->fetchTerms($arrTarget['terms_id'],'term',true,array(
			'filter'=>'t.deleted = 0'
		));
		
		$objIds = new ArrayObject(array($arrTarget['terms_id']));
		
		/*
		* recursive function to collect all child term ids 
		*/
		$func = create_function('$value,$index,$ids','if(strcmp(\'terms_id\',$index) == 0) $ids[] = $value;');
		
		/*
		* Collect all child ids 
		*/
		array_walk_recursive($arrTerms,$func,$objIds);
		
		/*
		* Collect ids into normal array to use implode 
		*/
		$arrIds = array();
		foreach($objIds as $intId) {
			$arrIds[] = $intId;
		}
		
		/*
		* Create SQL 
		*/
		$strSQL = sprintf(
			'UPDATE
			       MCP_TERMS
			    SET
			       MCP_TERMS.deleted = NULL
			  WHERE
			       MCP_TERMS.terms_id IN (%s)'
			,$this->_objMCP->escapeString(implode(',',$arrIds))
		);
		
		// echo "<p>$strSQL<p>";
		return $this->_objMCP->query($strSQL);
		
	}
	
	/*
	* Delete single term and move its children up one level
	* 
	* NOTE: This code is pretty much swipped from the removeLink method
	* inside the navigation DAO. It is pretty much the same process considering
	* the tree structure is the takes on a same form and similar dpenedent methods exist.
	* 
	* @param int terms id
	*/
	public function removeTerm($intTermsId) {
		
		/*
		* Get terms data 
		*/
		$arrTarget = $this->fetchTermById($intTermsId);
		
		/*
		* Get targets children
		*/
		$arrChildren = $this->fetchTerms($arrTarget['terms_id'],'term',false,array(
			'filter'=>'t.deleted = 0'
		));
		
		/*
		* Get targets siblings
		*/
		$arrTerms = $this->fetchTerms($arrTarget['parent_id'],$arrTarget['parent_type'],false,array(
			'filter'=>'t.deleted = 0'
		));
		
		/*
		* reorder array 
		*/
		$arrIds = array();
		
		foreach($arrTerms as $arrTerm) {
			
			/*
			* Replace links position with children 
			*/
			if($arrTerm['terms_id'] == $arrTarget['terms_id']) {
				foreach($arrChildren as $arrChild) {
					$arrIds[] = $arrChild['terms_id'];
				}
				continue;	
			}
			
			$arrIds[] = $arrTerm['terms_id'];
		}
		
		/*
		* Build update 
		*/
		$arrUpdate = array();
		foreach($arrIds as $intIndex=>$intId) {
			$arrUpdate[] = sprintf(
				"(%s,%s,'%s',%s)"
				,$this->_objMCP->escapeString($intId)
				,$this->_objMCP->escapeString($arrTarget['parent_id'])
				,$this->_objMCP->escapeString($arrTarget['parent_type'])
				,$this->_objMCP->escapeString($intIndex)
			);
		}
		
		/*
		* Build update query 
		*/
		$strSQL = sprintf(
			'INSERT IGNORE INTO MCP_TERMS (terms_id,parent_id,parent_type,weight) VALUES %s ON DUPLICATE KEY UPDATE parent_id=VALUES(parent_id),parent_type=VALUES(parent_type),weight=VALUES(weight)'
			,implode(',',$arrUpdate)
		);
		
		/*
		* Create delete query (soft-delete)
		*/
		$strSQLDelete = sprintf(
			'UPDATE 
			      MCP_TERMS
			    SET 
			      MCP_TERMS.deleted = NULL 
			  WHERE 
			      MCP_TERMS.terms_id = %s'
			,$this->_objMCP->escapeString($arrTarget['terms_id'])
		);
		
		/*
		* Delete link and update children 
		*/
		$this->_objMCP->query($strSQLDelete);
		$this->_objMCP->query($strSQL);
		// echo "<p>$strSQLDelete</p>";
		// echo "<p>$strSQL</p>";
		
		return 1;
		
	}
	
}
?>