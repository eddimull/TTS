// Navigation groups configuration for the main application menu
export const navigationGroups = [
  {
    id: 'scheduling',
    label: 'Scheduling',
    items: [
      {
        label: 'Booking',
        routeName: 'Bookings Home',
        permission: 'Bookings',
        activeMatch: (route) => route.indexOf('Booking') > -1
      },
      {
        label: 'Events',
        routeName: 'events',
        permission: 'Events',
        activeMatch: (route) => route === 'events'
      },
      {
        label: 'Rehearsals',
        routeName: 'rehearsal-schedules.index',
        permission: 'Rehearsals',
        activeMatch: (route) => route.includes('rehearsal')
      }
    ]
  },
  {
    id: 'finances',
    label: 'Finances',
    permission: 'Invoices',
    items: [
      {
        label: 'Overview',
        routeName: 'finances',
        activeMatch: (route) => route === 'finances'
      },
      {
        label: 'Revenue',
        routeName: 'Revenue',
        activeMatch: (route) => route === 'Revenue'
      },
      {
        label: 'Paid/Unpaid',
        routeName: 'Paid/Unpaid',
        activeMatch: (route) => route === 'Paid/Unpaid'
      },
      {
        label: 'Unpaid Services',
        routeName: 'Unpaid Services',
        activeMatch: (route) => route === 'Unpaid Services'
      },
      {
        label: 'Paid Services',
        routeName: 'Paid Services',
        activeMatch: (route) => route === 'Paid Services'
      },
      {
        label: 'Payments',
        routeName: 'Payments',
        activeMatch: (route) => route === 'Payments'
      },
      {
        label: 'Payout Calculator',
        routeName: 'Payout Calculator',
        activeMatch: (route) => route === 'Payout Calculator'
      }
    ]
  },
  {
    id: 'assets',
    label: 'Assets',
    items: [
      {
        label: 'Media Library',
        routeName: 'media.index',
        permission: 'Media',
        activeMatch: (route) => route === 'media.index'
      },
      {
        label: 'Chart Library',
        routeName: 'charts',
        permission: 'Charts',
        activeMatch: (route) => route === 'charts'
      }
    ]
  }
];

/**
 * Filter navigation items by user permissions
 * @param {Array} items - Navigation items to filter
 * @param {Object} navigation - User's navigation permissions object
 * @returns {Array} Filtered items the user has permission to see
 */
export function filterNavItemsByPermission(items, navigation) {
  return items.filter(item => {
    if (!item.permission) return true;
    return navigation?.[item.permission]?.read;
  });
}

/**
 * Check if a navigation group should be visible to the user
 * @param {Object} group - Navigation group to check
 * @param {Object} navigation - User's navigation permissions object
 * @returns {Boolean} True if group should be visible
 */
export function isGroupVisible(group, navigation) {
  if (group.permission) {
    return navigation?.[group.permission]?.read;
  }
  // If no group permission, show if any child has permission
  return filterNavItemsByPermission(group.items, navigation).length > 0;
}

/**
 * Check if a navigation group contains the current active page
 * @param {Object} group - Navigation group to check
 * @param {String} currentRoute - Current route name
 * @returns {Boolean} True if group contains active page
 */
export function isGroupActive(group, currentRoute) {
  return group.items.some(item => item.activeMatch(currentRoute));
}
