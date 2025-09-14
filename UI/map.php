<?php
    require_once('../config.php');
    require_once('header.php');
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<!-- Leaflet-Geosearch CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch/dist/geosearch.css"/>
<style>
html, body {
  height: 100%;
  margin: 0;
  padding: 0;
}

#map {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  z-index: 0; /* or higher if needed */
}

/* Set zoom control opacity */
.leaflet-control-zoom {
  opacity: 0.3;
}

/* Set attribution (and its link) opacity */
.leaflet-control-attribution {
  opacity: 0.2;
}

/* Apply your standard link style to Leaflet popup links */
.leaflet-popup-content a {
  color: #bbbb00 !important; /* Apply your standard link color */
  text-decoration: none; /* Remove underline */
}

.leaflet-popup-content a:visited {
  color: #bbbb00 !important; /* Same color for visited links */
}

.leaflet-popup-content a:hover {
  color: #757500 !important; /* Hover effect */
}

.pv-mode #menuIcon,
.pv-mode .leaflet-control-geosearch,
.pv-mode .leaflet-control-zoom,
.pv-mode #route-info {
  display: none !important;
}
</style>
<script>
    const urlParams = new URLSearchParams(window.location.search);
    const isPreview = urlParams.has('pv');
    if (isPreview) document.documentElement.classList.add('pv-mode');
    window.isPreview = isPreview;

    if (!isPreview) {
      document.addEventListener('DOMContentLoaded', () => {
        const menuIcon = document.getElementById('menuIcon');
        if (!menuIcon) return;
        menuIcon.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        menuIcon.style.display = 'inline-block';
        menuIcon.style.borderRadius = '50%';
        menuIcon.style.overflow = 'hidden';
      });
    }
</script>


<?php
    
  // Build the address string from session data if available
  $usedAddressParts = [
    $_SESSION['country']     ?? '',
    $_SESSION['city']        ?? '',
    $_SESSION['ZIPCode']     ?? '',
    trim(($_SESSION['street'] ?? '') . ' ' . ($_SESSION['HouseNumber'] ?? ''))
  ];
  $usedAddress = implode(', ', array_filter($usedAddressParts));

  // Keep the name logic on the original array (or switch to session if you’ve stored them there)
  $usedName  = $_SESSION['CompanyName']  ?? '';
  $usedIdpk  = $_SESSION['IdpkOfAdmin'] ?? '';
  $idpk      = $usedIdpk;





  // Get the address from the URL if provided
  $urlAddress = isset($_GET['address']) ? $_GET['address'] : '';
  // for example: ...&address=SomeCountry,+SomeCity+12345+SomeStreet+42
?>




<div id="map"></div>




<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<!-- Leaflet-Geosearch JS -->
<script src="https://unpkg.com/leaflet-geosearch/dist/geosearch.umd.js"></script>


<script>
  // Default map view: world view
  var defaultView = {
    center: [20, 0],
    zoom: 2
  };

  // Initialize the map without the default zoom control
  var map = L.map("map", { zoomControl: false }).setView(defaultView.center, defaultView.zoom);
  
  // Add the OpenStreetMap tile layer
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "OpenStreetMap"
  }).addTo(map);
  
  // only add zoom if not preview
  if (!isPreview) {
    L.control.zoom({ position: 'topright' }).addTo(map);
  }
  
  // Create custom icons
  var redIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

  var darkYellowIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-gold.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

  var yellowIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });
  
  // Set up the OpenStreetMap provider for geosearch
  const provider = new window.GeoSearch.OpenStreetMapProvider();
  
  // Create and add the search control to the map
  const searchControl = new window.GeoSearch.GeoSearchControl({
    provider: provider,
    style: "bar", // Options: "button" or "bar"
    autoComplete: true,
    autoCompleteDelay: 300,
    showMarker: true,
    showPopup: false,
  });

  // only add search bar if not preview
  if (!isPreview) {
    map.addControl(searchControl);
  }
  
  // When a location is found via the search control, update the marker's icon (GeoSearchControl will also center on it)
  map.on('geosearch/showlocation', function(e) {
    e.marker.setIcon(redIcon);
  });

  // Assemble the user address from PHP variables.
  var userAddress = "<?php echo ($_SESSION['country'] ?? '') . ', ' . ($_SESSION['city'] ?? '') . ', ' . ($_SESSION['ZIPCode'] ?? '') . ', ' . ($_SESSION['street'] ?? '') . ' ' . ($_SESSION['HouseNumber'] ?? ''); ?>";
  
  // Prepare an array to hold the search promises
  var searchPromises = [];

  // Object to store results
  var searchResults = {};

  // Search for user address if provided
  if (userAddress.trim() !== "") {
    var userPromise = provider.search({ query: userAddress })
      .then(function(results) {
        if (results && results.length > 0) {
          var lat = results[0].y;
          var lon = results[0].x;
          // Place a marker with the custom yellow icon at the user's address
          var userMarker = L.marker([lat, lon], { icon: yellowIcon }).addTo(map);
          userMarker.bindPopup("your home (127.0.0.1)<br><br>" + userAddress);
          searchResults.user = {lat: lat, lon: lon};
        }
      })
      .catch(function(error) {
        console.error("user address search failed:", error);
      });
    searchPromises.push(userPromise);
  }

  // If an "address" GET parameter is provided, search for that address.
  <?php if (!empty($urlAddress)): ?>
    var urlAddress = "<?php echo addslashes($urlAddress); ?>";
    var urlPromise = provider.search({ query: urlAddress })
      .then(function(results) {
        if (results && results.length > 0) {
          var lat = results[0].y;
          var lon = results[0].x;
          // Place a marker with the custom dark yellow icon at the found address
          var urlMarker = L.marker([lat, lon], { icon: redIcon }).addTo(map);
          urlMarker.bindPopup(urlAddress);
          searchResults.url = {lat: lat, lon: lon};
        }
      })
      .catch(function(error) {
        console.error("URL address search failed:", error);
      });
    searchPromises.push(urlPromise);
  <?php endif; ?>




  <?php if (!empty($idpk) && !empty($usedAddress)): ?>
      // Explorer/Creator address from PHP (populated when an idpk is provided)
      var explorerAddress = "<?php echo addslashes($usedAddress); ?>";

      // Search for the Explorer/Creator address and add a blue marker if found
      var explorerPromise = provider.search({ query: explorerAddress })
        .then(function(results) {
          if (results && results.length > 0) {
            var lat = results[0].y;
            var lon = results[0].x;
            var explorerMarker = L.marker([lat, lon], { icon: redIcon }).addTo(map);
            explorerMarker.bindPopup("<?php echo $usedName . ' (' . $usedIdpk . ')'; ?><br><br><?php echo $usedAddress; ?>");

            // Optionally, center the map on the Explorer/Creator's address:
            map.setView([lat, lon], 13);

            // Store the result if needed
            searchResults.explorer = {lat: lat, lon: lon};
          }
        })
        .catch(function(error) {
          console.error("search for your address failed:", error);
        });
      
      // Add this promise to the list if you need to wait for all searches:
      searchPromises.push(explorerPromise);
  <?php endif; ?>
























  
  // Initialize selected profile with car as default
  window.selectedProfile = 'driving';
  window.startPoint = null;
  window.endPoint = null;

  // Modified updateRoute function with profile support
  function updateRoute(start, end) {
      if (!start || !end) return;

      // Store current points for later recalculations
      window.startPoint = start;
      window.endPoint = end;

      var routeUrl = `https://router.project-osrm.org/route/v1/${window.selectedProfile}/${start[0]},${start[1]};${end[0]},${end[1]}?overview=full&geometries=geojson`;

      fetch(routeUrl)
          .then(response => response.json())
          .then(data => {
              if (data.routes && data.routes.length > 0) {
                  var route = data.routes[0];
                  var routeCoords = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);
              
                  if (window.currentRoute) {
                      map.removeLayer(window.currentRoute);
                  }
                
                  window.currentRoute = L.polyline(routeCoords, {
                      color: "blue",
                      weight: 4,
                      opacity: 0.5
                  }).addTo(map);
                
                  var distanceKm = (route.distance / 1000).toFixed(2);
                  var durationMin = (route.duration / 60).toFixed(0);

                  // Store the latest route details globally
                  window.currentDistance = distanceKm;
                  window.currentDuration = durationMin;
                
                  updateRouteInfo(distanceKm, durationMin);
              }
          })
          .catch(error => console.error("Route fetch error:", error));
  }

  // Modified updateRouteInfo with dynamic opacity
  function updateRouteInfo(distance, time) {
      if (window.isPreview) return;
      if (!document.getElementById('route-info')) {
          var routeInfoDiv = document.createElement('div');
          routeInfoDiv.id = 'route-info';
          routeInfoDiv.style.position = 'absolute';
          routeInfoDiv.style.bottom = '10px';
          routeInfoDiv.style.left = '10px';
          routeInfoDiv.style.backgroundColor = 'rgba(255, 255, 255, 0.5)';
          routeInfoDiv.style.padding = '10px';
          routeInfoDiv.style.borderRadius = '5px';
          routeInfoDiv.style.fontSize = '14px';
          routeInfoDiv.style.zIndex = '1000';
          document.body.appendChild(routeInfoDiv);
      }

      // Inside updateRouteInfo() where the emojis are defined:
      var routeInfo = document.getElementById('route-info');
      // Calculate hours and minutes
      var hours = Math.floor(time / 60);
      var minutes = time % 60;
          
      // Format the time string based on the hours
      var timeFormatted = hours > 0 ? `${hours}h ${minutes}min` : `${minutes}min`;

      routeInfo.innerHTML = `
          ${distance} km, ${timeFormatted}
          <!-- <br>
          <a href="#" class="emojiMenuLink" 
             data-profile="driving"
             style="font-size: 1.3rem; display: inline-block; opacity: ${window.selectedProfile === 'driving' ? '1' : '0.7'};">
              <span class="emojiMenuPart" title="plan route with car">🚗</span>
          </a>
          <a href="#" class="emojiMenuLink" 
             data-profile="bike"
             style="font-size: 1.3rem; display: inline-block; opacity: ${window.selectedProfile === 'bike' ? '1' : '0.7'};">
              <span class="emojiMenuPart" title="plan route with bicycle">🚲</span>
          </a>
          <a href="#" class="emojiMenuLink" 
             data-profile="foot"
             style="font-size: 1.3rem; margin-right: 0.2em; display: inline-block; opacity: ${window.selectedProfile === 'foot' ? '1' : '0.7'};">
              <span class="emojiMenuPart" title="plan walking route">🚶‍♂️</span>
          </a>
          <a href="#" class="emojiMenuLink" 
             style="font-size: 1.3rem; display: inline-block; opacity: 0.7;" 
             onclick="startNavigation(event)">
              <span class="emojiMenuPart" title="start navigation">▶️</span>
          </a> -->
          <a href="#" class="emojiMenuLink" 
             style="display: inline-block;" 
             onclick="startNavigation(event)">
              <span class="emojiMenuPart" title="start navigation">▶️</span>
          </a>
      `;
  }

  // Click handler for transportation modes
  document.addEventListener('click', function(e) {
      const link = e.target.closest('.emojiMenuLink');
      if (link) {
          e.preventDefault();
          const profile = link.dataset.profile;
          if (profile) { // Ensure it's a transportation link
              // Update selected profile
              window.selectedProfile = profile;

              // Update only the opacity for transportation mode links (excluding the start navigation button)
              document.querySelectorAll('.emojiMenuLink[data-profile]').forEach(el => {
                  el.style.opacity = el.dataset.profile === profile ? '1' : '0.7';
              });

              // Recalculate route
              if (window.startPoint && window.endPoint) {
                  updateRoute(window.startPoint, window.endPoint);

                  // Ensure the route info updates after the new route is fetched
                  setTimeout(() => {
                      updateRouteInfo((window.currentDistance || '...'), (window.currentDuration || '...'));
                  }, 500);
              }
          }
      }
  });

  function startNavigation(e) {
      e.preventDefault();
      if (window.currentRoute) {
          // Fit map to show entire route
          var bounds = window.currentRoute.getBounds();
          map.fitBounds(bounds);

          // Find and highlight the navigation button
          var navButton = document.querySelector('#route-info .emojiMenuPart[title="start navigation"]');

          if (navButton) {
              navButton.style.opacity = '1';

              // // Reset opacity when user interacts with map
              // function resetOpacity() {
              //     setTimeout(() => {
              //         navButton.style.opacity = '0.7';
              //     }, 1500); // Delay for 1.5 seconds
              //     map.off('moveend', resetOpacity);
              //     map.off('zoomend', resetOpacity);
              // }

              // map.on('moveend', resetOpacity);
              // map.on('zoomend', resetOpacity);
          }
      }
  }
  
  // Function to set the map's center based on search results
  function setMapCenter() {
      if (searchResults.explorer) {
          map.setView([searchResults.explorer.lat, searchResults.explorer.lon], 13);
      } else if (searchResults.url) {
          map.setView([searchResults.url.lat, searchResults.url.lon], 13);
      } else if (searchResults.user) {
          map.setView([searchResults.user.lat, searchResults.user.lon], 13);
      } else {
          console.log("No valid address found, using default view.");
      }
  }
  
  // Initial route calculation after all searches complete
  function handleSearchResults() {
      var start = searchResults.user ? [searchResults.user.lon, searchResults.user.lat] : null;
      var end = searchResults.explorer
          ? [searchResults.explorer.lon, searchResults.explorer.lat]
          : searchResults.url
          ? [searchResults.url.lon, searchResults.url.lat]
          : null;
  
      updateRoute(start, end);
  }
  
  // Main Promise handling
  Promise.all(searchPromises).then(function () {
      // Set the map center
      setMapCenter();
  
      // Handle route calculation
      handleSearchResults();
  });
  
  // Listen for new search results and update the route dynamically
  map.on('geosearch/showlocation', function (e) {
      var newMarkerCoords = [e.location.y, e.location.x];
  
      if (searchResults.user) {
          updateRoute([searchResults.user.lon, searchResults.user.lat], [e.location.x, e.location.y]);
      }
  });
</script>

