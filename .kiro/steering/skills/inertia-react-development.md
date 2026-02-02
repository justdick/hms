---
inclusion: fileMatch
fileMatchPattern: "resources/js/**/*.tsx"
---

# Inertia React Development

## When to Apply

Activate this skill when:

- Creating or modifying React page components for Inertia
- Working with forms in React (using `<Form>` or `useForm`)
- Implementing client-side navigation with `<Link>` or `router`
- Using v2 features: deferred props, prefetching, or polling
- Building React-specific features with the Inertia protocol

## Documentation

Use `search-docs` for detailed Inertia v2 React patterns and documentation.

## Basic Usage

### Page Components Location

React page components should be placed in the `resources/js/Pages` directory.

### Page Component Structure

```tsx
export default function UsersIndex({ users }) {
    return (
        <div>
            <h1>Users</h1>
            <ul>
                {users.map(user => <li key={user.id}>{user.name}</li>)}
            </ul>
        </div>
    )
}
```

## Client-Side Navigation

### Basic Link Component

Use `<Link>` for client-side navigation instead of traditional `<a>` tags:

```tsx
import { Link, router } from '@inertiajs/react'

<Link href="/">Home</Link>
<Link href="/users">Users</Link>
<Link href={`/users/${user.id}`}>View User</Link>
```

### Link with Method

```tsx
import { Link } from '@inertiajs/react'

<Link href="/logout" method="post" as="button">
    Logout
</Link>
```

### Prefetching

Prefetch pages to improve perceived performance:

```tsx
import { Link } from '@inertiajs/react'

<Link href="/users" prefetch>
    Users
</Link>
```

### Programmatic Navigation

```tsx
import { router } from '@inertiajs/react'

function handleClick() {
    router.visit('/users')
}

// Or with options
router.visit('/users', {
    method: 'post',
    data: { name: 'John' },
    onSuccess: () => console.log('Success!'),
})
```

## Form Handling

### Form Component (Recommended)

The recommended way to build forms is with the `<Form>` component:

```tsx
import { Form } from '@inertiajs/react'

export default function CreateUser() {
    return (
        <Form action="/users" method="post">
            {({ errors, processing, wasSuccessful }) => (
                <>
                    <input type="text" name="name" />
                    {errors.name && <div>{errors.name}</div>}

                    <input type="email" name="email" />
                    {errors.email && <div>{errors.email}</div>}

                    <button type="submit" disabled={processing}>
                        {processing ? 'Creating...' : 'Create User'}
                    </button>

                    {wasSuccessful && <div>User created!</div>}
                </>
            )}
        </Form>
    )
}
```

### Form Component Reset Props

The `<Form>` component supports automatic resetting:

- `resetOnError` - Reset form data when the request fails
- `resetOnSuccess` - Reset form data when the request succeeds
- `setDefaultsOnSuccess` - Update default values on success

### `useForm` Hook

For more programmatic control, use the `useForm` hook:

```tsx
import { useForm } from '@inertiajs/react'

export default function CreateUser() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
    })

    function submit(e) {
        e.preventDefault()
        post('/users', {
            onSuccess: () => reset('password'),
        })
    }

    return (
        <form onSubmit={submit}>
            <input
                type="text"
                value={data.name}
                onChange={e => setData('name', e.target.value)}
            />
            {errors.name && <div>{errors.name}</div>}
            <button type="submit" disabled={processing}>
                Create User
            </button>
        </form>
    )
}
```

## Inertia v2 Features

### Deferred Props

Use deferred props to load data after initial page render:

```tsx
export default function UsersIndex({ users }) {
    return (
        <div>
            <h1>Users</h1>
            {!users ? (
                <div className="animate-pulse">
                    <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div className="h-4 bg-gray-200 rounded w-1/2"></div>
                </div>
            ) : (
                <ul>
                    {users.map(user => (
                        <li key={user.id}>{user.name}</li>
                    ))}
                </ul>
            )}
        </div>
    )
}
```

### Polling

Automatically refresh data at intervals:

```tsx
import { router } from '@inertiajs/react'
import { useEffect } from 'react'

export default function Dashboard({ stats }) {
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['stats'] })
        }, 5000)
        return () => clearInterval(interval)
    }, [])

    return <div>Active Users: {stats.activeUsers}</div>
}
```

### WhenVisible (Infinite Scroll)

Load more data when user scrolls to a specific element:

```tsx
import { WhenVisible } from '@inertiajs/react'

export default function UsersList({ users }) {
    return (
        <div>
            {users.data.map(user => (
                <div key={user.id}>{user.name}</div>
            ))}

            {users.next_page_url && (
                <WhenVisible
                    data="users"
                    params={{ page: users.current_page + 1 }}
                    fallback={<div>Loading more...</div>}
                />
            )}
        </div>
    )
}
```

## Common Pitfalls

- Using traditional `<a>` links instead of Inertia's `<Link>` component (breaks SPA behavior)
- Forgetting to add loading states (skeleton screens) when using deferred props
- Not handling the `undefined` state of deferred props before data loads
- Using `<form>` without preventing default submission (use `<Form>` component or `e.preventDefault()`)
