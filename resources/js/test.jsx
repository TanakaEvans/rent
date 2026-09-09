import { createRoot } from 'react-dom/client';

// Simple test component
function TestApp() {
    return (
        <div style={{ padding: '20px', fontFamily: 'Arial, sans-serif' }}>
            <h1 style={{ color: 'blue' }}>React Test - Working!</h1>
            <p>If you can see this, React is working properly.</p>
            <button onClick={() => alert('Button clicked!')}>Test Button</button>
        </div>
    );
}

// Mount the test app
const el = document.getElementById('app');
if (el) {
    createRoot(el).render(<TestApp />);
} else {
    console.error('App element not found');
}
