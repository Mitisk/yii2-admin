# jQuery Menu Editor for Bootstrap 5
### Features
* Add, Update & Remove items from Menu
* Multilevel Drag & Drop
* Form Item Editor
* Include IconPicker Plugin (https://victor-valencia.github.io/bootstrap-iconpicker)
* Support for mobile devices
* Load data from JSON string
* The output is a JSON string

This project is based on jQuery Menu Editor (v1.1.1) https://github.com/davicotico/jquery-menu-editor and added Bootstrap 5 support.

# Documentation

## Requirements
* Bootstrap 5.x
* jQuery >= 3.x
* Fontawesome 5.3.1
* Bootstrap Iconpicker

## How to use
### Include the CSS and Scripts
```html
<!-- CSS files in the head section -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css"/>
<link rel="stylesheet" href="bootstrap-iconpicker/css/bootstrap-iconpicker.min.css">

<!-- JavaScript files just before closing body tag -->
<script type="text/javascript" src='https://code.jquery.com/jquery-3.7.1.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
<script type="text/javascript" src="bootstrap-iconpicker/js/iconset/fontawesome5-3-1.min.js"></script>
<script type="text/javascript" src="bootstrap-iconpicker/js/bootstrap-iconpicker.min.js"></script>
<script type="text/javascript" src="jquery-menu-editor.min.js"></script>
```

### Creating the Drag & Drop List
```html
<ul id="myEditor" class="sortableLists list-group">
</ul>
```

### Creating the Form
* All form inputs should have the class `item-menu`
* The icon picker should have the id=[LIST_ID]+"_icon"

```html
<div class="card border-primary mb-3">
    <div class="card-header bg-primary text-white">Edit item</div>
    <div class="card-body">
        <form id="frmEdit" class="form-horizontal">
            <div class="mb-3">
                <label for="text" class="form-label">Text</label>
                <div class="input-group">
                    <input type="text" class="form-control item-menu" name="text" id="text" placeholder="Text">
                    <button type="button" id="myEditor_icon" class="btn btn-outline-secondary"></button>
                </div>
                <input type="hidden" name="icon" class="item-menu">
            </div>
            <div class="mb-3">
                <label for="href" class="form-label">URL</label>
                <input type="text" class="form-control item-menu" id="href" name="href" placeholder="URL">
            </div>
            <div class="mb-3">
                <label for="target" class="form-label">Target</label>
                <select name="target" id="target" class="form-control item-menu">
                    <option value="_self">Self</option>
                    <option value="_blank">Blank</option>
                    <option value="_top">Top</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="title" class="form-label">Tooltip</label>
                <input type="text" name="title" class="form-control item-menu" id="title" placeholder="Tooltip">
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" id="btnUpdate" class="btn btn-primary" disabled>
            <i class="fas fa-sync-alt"></i> Update
        </button>
        <button type="button" id="btnAdd" class="btn btn-success">
            <i class="fas fa-plus"></i> Add
        </button>
    </div>
</div>
```

### Initialize the Menu Editor
```javascript
// Icon picker options
var iconPickerOptions = {
    searchText: "Search...", 
    labelHeader: "{0}/{1}"
};

// Sortable list options
var sortableListOptions = {
    placeholderCss: {'background-color': "#cccccc"}
};

// Initialize the menu editor
var editor = new MenuEditor('myEditor', { 
    listOptions: sortableListOptions, 
    iconPicker: iconPickerOptions,
    maxLevel: 2 // (Optional) Default is -1 (no level limit)
    // Valid levels are from [0, 1, 2, 3,...N]
});

// Set up the editor
editor.setForm($('#frmEdit'));
editor.setUpdateButton($('#btnUpdate'));

// Update button click handler
$("#btnUpdate").click(function(){
    editor.update();
});

// Add button click handler
$('#btnAdd').click(function(){
    editor.add();
});
```

### Loading Data from JSON
Use the `setData` method to load menu items:
```javascript
var arrayjson = [
    {
        "href": "http://home.com",
        "icon": "fas fa-home",
        "text": "Home",
        "target": "_top",
        "title": "My Home"
    },
    {
        "icon": "fas fa-chart-bar",
        "text": "Option 2"
    },
    {
        "icon": "fas fa-bell",
        "text": "Option 3"
    },
    {
        "icon": "fas fa-search",
        "text": "Option 4",
        "children": [
            {
                "icon": "fas fa-plug",
                "text": "Sub Option 1",
                "children": [
                    {
                        "icon": "fas fa-filter",
                        "text": "Sub Sub Option 1"
                    }
                ]
            }
        ]
    }
];

editor.setData(arrayjson);
```

### Getting the Menu Data
Use the `getString` method to get the menu data as a JSON string:
```javascript
var str = editor.getString();
$("#myTextarea").text(str);
