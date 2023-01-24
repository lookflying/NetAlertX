<?php

  require 'php/templates/header.php';
  require 'php/server/db.php';
  require 'php/server/util.php';

  // online / offline badges HTML snippets 
  define('badge_online', '<div class="badge bg-green text-white" style="width: 60px;">Online</div>');
  define('badge_offline', '<div class="badge bg-red text-white" style="width: 60px;">Offline</div>');
  define('circle_online', '<div class="badge bg-green text-white" style="width: 10px; height: 10px; padding:2px; margin-top: -25px;">&nbsp;</div>');
  define('circle_offline', '<div class="badge bg-red text-white" style="width: 10px;  height: 10px; padding:2px; margin-top: -25px;">&nbsp;</div>');
  
  $NETWORKTYPES = getNetworkTypes();  
?>

<!-- Page ------------------------------------------------------------------ -->
<div class="content-wrapper">

  <!-- Content header--------------------------------------------------------- -->
  <section class="content-header">
  <?php require 'php/templates/notification.php'; ?>
    <h1 id="pageTitle">
      <i class="fa fa-network-wired"></i> <?php echo lang('Network_Title');?>
    </h1>
  </section>



  <div id="networkTree" ></div>

  <!-- Main content ---------------------------------------------------------- -->
  <section class="content">
    <?php
      // Create top-level node (network devices) tabs 
      function createDeviceTabs($node_mac, $node_name, $node_status, $node_type, $node_ports_count, $icon, $activetab) {        

        // prepare string with port number in brackets if available
        $str_port = "";
        if ($node_ports_count != "") {
          $str_port = ' ('.$node_ports_count.')';
        }

        // online/offline status circle (red/green)
        $node_badge = "";
        if($node_status == 1) // 1 means online, 0 offline
        {
          $node_badge = circle_online; 
        } else
        {
          $node_badge = circle_offline;
        }

        if($icon != '')
        {
          $icon = '<i class="fa fa-'.$icon.'"></i> ';
        }
        
        $idFromMac = str_replace(":", "_", $node_mac);
        $str_tab_header = '<li class="'.$activetab.'">
                              <a href="#'.$idFromMac.'" data-mytabmac="'.$node_mac.'" id="'.$idFromMac.'_id" data-toggle="tab" >' // _id is added so it doesn't conflict with AdminLTE tab behavior
                                .$icon.$node_name.' ' .$str_port.$node_badge.
                              '</a>
                          </li>';

        echo $str_tab_header;

      }

      // Create pane content (displayed inside of the tabs)      
      function createPane($node_mac, $node_name, $node_status, $node_type, $node_ports_count, $node_parent_mac, $activetab){        

        // online/offline status circle (red/green)
        $node_badge = "";
        if($node_status == 1) // 1 means online, 0 offline
        {
          $node_badge = badge_online; 
        } else
        {
          $node_badge = badge_offline;
        }

        $idFromMac = str_replace(":", "_", $node_mac);
        $idParentMac = str_replace(":", "_", $node_parent_mac);
        $str_tab_pane = '<div class="tab-pane '.$activetab.'" id="'.$idFromMac.'">
                            <div>
                              <h2 class="page-header"><i class="fa fa-server"></i> '.lang('Network_Node'). '</h2>
                            </div>
                            <table class="table table-striped" > 
                              <tbody>
                                <tr> 
                                  <td class="col-sm-3">
                                    <b>'.lang('Network_Node').'</b>
                                  </td>
                                  <td  class="anonymize">
                                    <a href="./deviceDetails.php?mac='.$node_mac.'">
                                    '.$node_name.'
                                    </a>
                                  </td>
                                </tr>
                                <tr> 
                                  <td >
                                    <b>MAC</b>
                                  </td>
                                  <td data-mynodemac="'.$node_mac.'" class="anonymize">'
                                    .$node_mac.
                                  '</td>
                                </tr>
                                <tr>
                                  <td>
                                    <b>'.lang('Device_TableHead_Type').'</b>
                                  </td>
                                  <td>
                                  ' .$node_type. '
                                  </td>
                                </tr>
                                <tr> 
                                  <td>
                                    <b>'.lang('Network_Table_State').'</b> 
                                  </td>
                                  <td>  '
                                    .$node_badge.
                                  '</td>
                                </tr>
                                <tr> 
                                  <td>
                                    <b>'.lang('Network_Parent').'</b> 
                                  </td>
                                  <td>  
                                    <a onclick="setCache(\'activeNetworkTab\',\''.$idParentMac.'_id\')" href="./network.php">
                                      <b class="anonymize">'.$idParentMac.'  <i class="fa fa-square-up-right"></i></b>
                                    </a>                                 
                                  </td>
                              </tr>
                              </tbody>
                            </table>
                            <br>
                             <div id="assignedDevices"  class="box-body no-padding">
                              <div class="page-header">
                                <h3>
                                  <i class="fa fa-sitemap"></i> '.lang('Network_Connected').'
                                </h3>
                              </div>
                             ';

        $str_table =      '   <table class="table table-striped">
                                <tbody>
                                <tr>
                                  <th class="col-sm-1" >Port</th>
                                  <th class="col-sm-1" >'.lang('Network_Table_State').'</th>
                                  <th class="col-sm-2" >'.lang('Network_Table_Hostname').'</th>
                                  <th class="col-sm-1" >'.lang('Network_Table_IP').'</th>
                                  <th class="col-sm-3" >'.lang('Network_ManageLeaf').'</th>
                                </tr>';
        
        // Prepare Array for Devices with Port value
        // If no Port is set, the Port number is set to 0
        if ($node_ports_count == "") {
          $node_ports_count = 0;
        }

        // Get all leafs connected to a node based on the node_mac        
        $func_sql = 'SELECT dev_Network_Node_port as port,
                            dev_MAC as mac,  
                            dev_PresentLastScan as online, 
                            dev_Name as name,
                            dev_DeviceType as type, 
                            dev_LastIP as last_ip,
                            (select dev_DeviceType from Devices a where dev_MAC = "'.$node_mac.'") as node_type
                        FROM Devices WHERE dev_Network_Node_MAC_ADDR = "'.$node_mac.'" order by port, name asc';
        
        global $db;
        $func_result = $db->query($func_sql);  
        
        // array 
        $tableData = array();
        while ($row = $func_result -> fetchArray (SQLITE3_ASSOC)) {   
            // Push row data      
            $tableData[] = array( 'port'            => $row['port'], 
                                  'mac'             => $row['mac'],
                                  'online'          => $row['online'],
                                  'name'            => $row['name'],
                                  'type'            => $row['type'],
                                  'last_ip'         => $row['last_ip'],
                                  'node_type'       => $row['node_type']); 
        }
    
        // Control no rows
        if (empty($tableData)) {
          $tableData = [];
        }

        $str_table_rows = "";        

        foreach ($tableData as $row) {                            
         
          if ($row['online'] == 1) {
            $port_state = badge_online;
          } else {
            $port_state = badge_offline;
          }
                    
          // prepare HTML for the port table column cell
          $port_content = "N/A";
  
          if (($row['node_type'] == "WLAN" || $row['node_type'] == "AP" ) && ($row['port'] == NULL || $row['port'] == "") ){ 
            $port_content = '<i class="fa fa-wifi"></i>';
          } elseif ($row['node_type'] == "Powerline") 
          {
            $port_content = '<i class="fa fa-flash"></i>';
          } elseif ($row['port'] != NULL && $row['port'] != "") 
          {
            $port_content = $row['port'];
          }
  
          $str_table_rows = $str_table_rows.
                            '<tr>
                              <td style="text-align: center;">
                                '.$port_content.'                  
                              </td>
                              <td>'
                                .$port_state.
                              '</td>
                              <td style="padding-left: 10px;">
                                <a href="./deviceDetails.php?mac='.$row['mac'].'">
                                  <b class="anonymize">'.$row['name'].'</b>
                                </a>
                              </td>
                              <td class="anonymize">'
                                .$row['last_ip'].
                              '</td>
                              <td class="">
                                <button class="btn btn-primary btn-danger btn-sm" data-myleafmac="'.$row['mac'].'" >'.lang('Network_ManageUnassign').'</button>
                              </td>
                            </tr>';
          
        }        

        $str_table_close =    '</tbody>
                            </table>';

        // no connected device - don't render table, just display some info
        if($str_table_rows == "")
        {
          $str_table = "<div>                        
                          <div>
                            ".lang("Network_NoAssignedDevices")."
                          </div>
                        </div>";
          $str_table_close = "";
        }
         
        $str_close_pane = '</div>       
          </div>';  

        // write the HTML
        echo  ''.$str_tab_pane.
                  $str_table.
                  $str_table_rows.
                  $str_table_close.
                  $str_close_pane;
      }     

    
    // Create Top level tabs   (List of network devices), explanation of the terminology below:
    //
    //             Switch 1 (node) 
    //              /(p1)    \ (p2)     <----- port numbers
    //             /          \
    //   Smart TV (leaf)      Switch 2 (node (for the PC) and leaf (for Switch 1))
    //                          \
    //                          PC (leaf)
    
    $sql = "SELECT node_name, node_mac, online, node_type, node_ports_count, parent_mac, node_icon
            FROM 
            (
                  SELECT  a.dev_Name as  node_name,        
                        a.dev_MAC as node_mac,
                        a.dev_PresentLastScan as online,
                        a.dev_DeviceType as node_type,
                        a.dev_Network_Node_MAC_ADDR as parent_mac,
                        a.dev_Icon as node_icon
                  FROM Devices a 
                  WHERE a.dev_DeviceType in ('AP', 'Gateway', 'Firewall', 'Powerline', 'Switch', 'WLAN', 'PLC', 'Router','USB LAN Adapter', 'USB WIFI Adapter', 'Internet')					
            ) t1
            LEFT JOIN
            (
                  SELECT  b.dev_Network_Node_MAC_ADDR as node_mac_2,
                        count() as node_ports_count 
                  FROM Devices b 
                  WHERE b.dev_Network_Node_MAC_ADDR NOT NULL group by b.dev_Network_Node_MAC_ADDR
            ) t2
            ON (t1.node_mac = t2.node_mac_2);
          ";

    $result = $db->query($sql);    

    // array 
    $tableData = array();
    while ($row = $result -> fetchArray (SQLITE3_ASSOC)) {   
        // Push row data      
        $tableData[] = array( 'node_mac'                => $row['node_mac'], 
                              'node_name'               => $row['node_name'],
                              'online'                  => $row['online'],
                              'node_type'               => $row['node_type'],
                              'parent_mac'              => $row['parent_mac'],
                              'node_icon'               => $row['node_icon'],
                              'node_ports_count'        => $row['node_ports_count']);
    }

    // Control no rows
    if (empty($tableData)) {
      $tableData = [];
    }

    echo '<div class="nav-tabs-custom" style="margin-bottom: 0px;">
    <ul class="nav nav-tabs">';

    $activetab='active';
    foreach ($tableData as $row) {                            
        createDeviceTabs( $row['node_mac'], 
                          $row['node_name'], 
                          $row['online'],
                          $row['node_type'], 
                          $row['node_ports_count'],
                          $row['node_icon'],
                          $activetab);

                          $activetab = ""; // reset active tab indicator, only the first tab is active
      
    }
    echo ' </ul>  <div class="tab-content">';

    $activetab='active';    

    foreach ($tableData as $row) {                            
      createPane($row['node_mac'], 
                  $row['node_name'],
                  $row['online'], 
                  $row['node_type'], 
                  $row['node_ports_count'],
                  $row['parent_mac'],
                  $activetab);

                  $activetab = ""; // reset active tab indicator, only the first tab is active
    
    }

    ?>
    <!-- /.tab-pane -->
    </div>         
  </section>

  <!-- Unassigned devices -->
  <?php   

    // Get all Unassigned / unconnected nodes 
    $func_sql = 'SELECT dev_MAC as mac,  
                        dev_PresentLastScan as online, 
                        dev_Name as name,                        
                        dev_LastIP as last_ip,
                        dev_Network_Node_MAC_ADDR
                    FROM Devices WHERE (dev_Network_Node_MAC_ADDR is null or dev_Network_Node_MAC_ADDR = "" or dev_Network_Node_MAC_ADDR = " " or dev_Network_Node_MAC_ADDR = "undefined") and dev_MAC not like "%internet%" order by name asc'; 

    global $db;
    $func_result = $db->query($func_sql);  

    // array 
    $tableData = array();
    while ($row = $func_result -> fetchArray (SQLITE3_ASSOC)) {   
      // Push row data      
      $tableData[] = array( 'mac'             => $row['mac'],
                            'online'          => $row['online'],
                            'name'            => $row['name'],
                            'last_ip'         => $row['last_ip']); 
    }

    // Don't do anything if empty
    if (!(empty($tableData))) {
      $str_table_header =  '
                        <div class="content">
                          <div id="unassignedDevices" class="box box-aqua box-body">
                            <section> 
                              <h3>
                                <i class="fa fa-laptop"></i> '.lang('Network_UnassignedDevices').'
                              </h3>
                              <table class="table table-striped">
                                <tbody>
                                <tr>                              
                                  <th  class="col-sm-1" ></th>
                                  <th  class="col-sm-1" >'.lang('Network_Table_State').'</th>
                                  <th  class="col-sm-2" >'.lang('Network_Table_Hostname').'</th>
                                  <th  class="col-sm-1" >'.lang('Network_Table_IP').'</th>
                                  <th  class="col-sm-3" >'.lang('Network_Assign').'</th>
                                </tr>';   

      $str_table_rows = "";        

      foreach ($tableData as $row) {  
        
        if ($row['online'] == 1) {
          $state = badge_online;
        } else {
          $state = badge_offline;
        }

        $str_table_rows = $str_table_rows.
                                          '
                                          <tr>                  
                                            <td> </td> 
                                            <td>'
                                              .$state.
                                            '</td>
                                            <td style="padding-left: 10px;">
                                              <a href="./deviceDetails.php?mac='.$row['mac'].'">
                                                <b class="anonymize">'.$row['name'].'</b>
                                              </a>
                                            </td>
                                            <td>'
                                              .$row['last_ip'].
                                            '</td>                                            
                                            <td>
                                            <button class="btn btn-primary btn-sm" data-myleafmac="'.$row['mac'].'" >'.lang('Network_ManageAssign').'</button>
                                          </td>
                                          </tr>';
      }        

      $str_table_close =    '</tbody>
                          </table>
                        </section>
                      </div>
                    </div>';

      // write the html
      echo $str_table_header.$str_table_rows.$str_table_close;     
    }


  ?>
    
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

<!-- ----------------------------------------------------------------------- -->



<?php
  require 'php/templates/footer.php';
?>

<script src="lib/treeviz/index.js"></script>
<script src="lib/treeviz/require.js"></script>
<script src="js/pialert_common.js"></script>

<script>
  $.get('php/server/devices.php?action=getDevicesList&status=all&forceDefaultOrder', function(data) {     

      rawData = JSON.parse (data)
      console.log(rawData)

      devicesListnew = rawData["data"].map(item =>  { return {
                                                              "name":item[0], 
                                                              "type":item[2], 
                                                              "icon":item[3], 
                                                              "mac":item[11], 
                                                              "parentMac":item[14], 
                                                              "rowid":item[13], 
                                                              "status":item[10] 
                                                              }})

      setCache('devicesListNew', JSON.stringify(devicesListnew))

      console.log(devicesListnew)

      // create tree
      initTree(getHierarchy());

      // attach on-click events
      attachTreeEvents();
    });
</script>


<script defer>
  // ---------------------------------------------------------------------------
  // Tree functionality
  // ---------------------------------------------------------------------------
  // ---------------------------------------------------------------------------  
  function getDevicesList()
  {
    // Read cache
    devicesList = getCache('devicesListNew');
    
    if (devicesList != '') {
        devicesList = JSON.parse (devicesList);
    } else {
        devicesList = [];
    }
    return devicesList;
  }

  
  // ---------------------------------------------------------------------------
  var leafNodesCount = 0;
  var treeLoadedAlready = false;
  var hiddenMacs = [];

  function getChildren(node, list, path)
  {
    var children = [];

    // loop thru all items and find childern...
    for(var i in list)
    {
      //... of the current node
      if(list[i].parentMac == node.mac && !hiddenMacs.includes(list[i].parentMac))
      {        
        // and process them 
        children.push(getChildren(list[i], list, path + ((path == "") ? "" : '|') + list[i].parentMac, hiddenMacs))
      }
    }

    // note the total number of leaf nodes to calculate the font scaling
    if(!treeLoadedAlready && children.length == 0) 
    { 
      leafNodesCount++ 
    }
    
    return { 
      name: node.name,      
      path: path,
      mac: node.mac,
      parentMac: node.parentMac,
      icon: node.icon,
      type: node.type,
      status: node.status,
      hasChildren: children.length > 0 || hiddenMacs.includes(node.mac),
      hiddenChildren: hiddenMacs.includes(node.mac),
      qty: children.length,
      children:  children
    };
        
  }

  // ---------------------------------------------------------------------------
  
  function getHierarchy()
  {    
    list = getDevicesList();
    
    for(i in list)
    {      
      if(list[i].mac == 'Internet')
      { 
        return (getChildren(list[i], list, ''))
        break;
      }
    }
  }


  // ---------------------------------------------------------------------------
  function toggleSubTree(parentMac, treePath)
  {
    treePath = treePath.split('|')

    if(!hiddenMacs.includes(parentMac))
    {
      hiddenMacs.push(parentMac)
    }
    else
    {
      removeItemFromArray(hiddenMacs, parentMac)
    }

    list = getDevicesList();

    // updatedTree = myHierarchy;
    updatedTree = getHierarchy()

    myTree.refresh(updatedTree);

    // re-attach any onclick events
    attachTreeEvents();   
  }

  // --------------------------------------------------------------------------- 
  function attachTreeEvents()
  {
    //  toggle subtree functionality
    $("div[data-mytreemac]").each(function(){
        $(this).attr('onclick', 'toggleSubTree("'+$(this).attr('data-mytreemac')+'","'+ $(this).attr('data-mytreepath')+'")')
    }); 
  }

  // --------------------------------------------------------------------------- 
  var myTree;
  var treeAreaHeight = 600;
  function initTree(myHierarchy)
  {    
    // to prevent font scaling everytime we collapse/expand a subtree
    treeLoadedAlready = true;

    $("#networkTree").attr('style', "height:"+treeAreaHeight+"px; width:1070px")

    myTree = Treeviz.create({
      htmlId: "networkTree",
      
      renderNode:  nodeData =>  { 

        // calculate the font size of the leaf nodes to fit everything into the tree area
        leafNodesCount == 0 ? 1 : leafNodesCount;
        emSize = ((600/(20*leafNodesCount)).toFixed(2));        
        emSize = emSize > 1 ? 1 : emSize;
        
        var fontSize = (nodeData.data.hasChildren) ? "" : "font-size:"+emSize+"em;";

        deviceIcon = (!emptyArr.includes(nodeData.data.icon )) ?  "<div class='netIcon '><i class='fa fa-"+nodeData.data.icon +"'></i></div>"    : "";
        collapseExpandIcon = nodeData.data.hiddenChildren ?  "square-plus" :"square-minus";
        collapseExpandHtml = (nodeData.data.hasChildren) ?  "<div class='netCollapse' data-mytreepath='"+nodeData.data.path+"' data-mytreemac='"+nodeData.data.mac+"'><i class='fa fa-"+ collapseExpandIcon +" pointer'></i></div>"    : "";
        statusCss = " netStatus-" + nodeData.data.status;

        selectedNodeMac = $(".nav-tabs-custom .active a").attr('data-mytabmac')

        highlightedCss = nodeData.data.mac == selectedNodeMac ? " highlightedNode" : "";        

        return result = "<div class='box "+statusCss+" "+highlightedCss+"'  data-mytreemacmain='"+nodeData.data.mac+"' \
                          style='height:"+nodeData.settings.nodeHeight+"px;\
                                 width:180px;\
                                 display:flex;\
                                 flex-direction:column;\
                                 justify-content:center;\
                                 " + fontSize + "\
                                 align-items:center;\
                                 background-color:" +nodeData.data.color+";\
                                 border-radius:5px;'\
                          >\
                          <div class='netNodeText '>\
                            <strong>" + deviceIcon +
                              "<span class='spanNetworkTree anonymizeDev'>"+nodeData.data.name+"</span>\
                            </strong>"
                            +collapseExpandHtml+ 
                          "</div></div>";
        },

      onNodeClick:  nodeData =>  { 
          // console.log(this)  
        },
      mainAxisNodeSpacing: 'auto',
      secondaryAxisNodeSpacing: 0.3,
      nodeHeight: '25',    
      marginTop: '5',
      hasZoom: false,
      hasPan: false,
      // marginLeft: '15',
      idKey: "name",
      hasFlatData: false,
      linkWidth: (nodeData) => 3,
      relationnalField: "children",
      });

      myTree.refresh(myHierarchy);
      
    }

  // ---------------------------------------------------------------------------
  // Tabs functionality
  // ---------------------------------------------------------------------------
  // Register events on tab change
  
  $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {   

    initButtons()

  });


  // ---------------------------------------------------------------------------
  function initTab()
  {
    key = "activeNetworkTab"

    // default selection
    selectedTab = "Internet_id"

    // the #target from the url
    target = window.location.hash.substr(1)  

    // update cookie if target specified
    if(target != "")
    {      
      setCache(key, target+'_id') // _id is added so it doesn't conflict with AdminLTE tab behavior
    }

    // get the tab id from the cookie (already overriden by the target)
    if(!emptyArr.includes(getCache(key)))
    {
      selectedTab = getCache(key);
    }

    // Activate panel
    $('.nav-tabs a[id='+ selectedTab +']').tab('show');

    // When changed save new current tab
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
      setCache(key, $(e.target).attr('id'))
    });
    
  }

  // ---------------------------------------------------------------------------
  function initButtons()
  { 

    var currentNodeMac = $(".tab-content .active td[data-mynodemac]").attr('data-mynodemac'); 

    // change highlighted node in the tree
    selNode = $("#networkTree .highlightedNode")[0]

    // console.log(selNode)

    if(selNode)
    {
      $(selNode).attr('class',  $(selNode).attr('class').replace('highlightedNode'))
    }

    newSelNode = $("#networkTree div[data-mytreemacmain='"+currentNodeMac+"']")[0]
    
    $(newSelNode).attr('class',  $(newSelNode).attr('class') + ' highlightedNode')


    // init the Assign buttons
    $('#unassignedDevices  button[data-myleafmac]').each(function(){
      $(this).attr('onclick', 'updateLeaf("'+$(this).attr('data-myleafmac')+'","'+currentNodeMac+'")')
    }); 

    // init Unassign buttons
    $('#assignedDevices button[data-myleafmac]').each(function(){
      $(this).attr('onclick', 'updateLeaf("'+$(this).attr('data-myleafmac')+'","")')
    }); 
  }

  // ---------------------------------------------------------------------------
  function updateLeaf(leafMac,nodeMac)
  {
    console.log(leafMac)
    console.log(nodeMac)
    saveData('updateNetworkLeaf', leafMac, nodeMac);
    setTimeout("location.reload();", 1000); // refresh page after 1s
  }


  // init selected (first) tab
  initTab();  

  // init Assign/Unassign buttons
  initButtons()


</script>



