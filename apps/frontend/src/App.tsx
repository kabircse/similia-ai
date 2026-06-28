import { useState } from "react";
import { getMe, login, logout } from "./lib/api";

function App() {
  const [result, setResult] = useState<string>("");

  const handleLogin = async () => {
    try {
      const data = await login("doctor@similia.test", "password");
      setResult(JSON.stringify(data, null, 2));
    } catch (error: any) {
      setResult(JSON.stringify(error.response?.data ?? error.message, null, 2));
    }
  };

  const handleMe = async () => {
    try {
      const data = await getMe();
      setResult(JSON.stringify(data, null, 2));
    } catch (error: any) {
      setResult(JSON.stringify(error.response?.data ?? error.message, null, 2));
    }
  };

  const handleLogout = async () => {
    try {
      const data = await logout();
      setResult(JSON.stringify(data, null, 2));
    } catch (error: any) {
      setResult(JSON.stringify(error.response?.data ?? error.message, null, 2));
    }
  };

  return (
    <main style={{ padding: 32 }}>
      <h1>Similia AI Auth Test</h1>

      <button onClick={handleLogin}>Login as Demo Doctor</button>
      <button onClick={handleMe} style={{ marginLeft: 12 }}>
        Get Current User
      </button>
      <button onClick={handleLogout} style={{ marginLeft: 12 }}>
        Logout
      </button>

      <pre style={{ marginTop: 24 }}>{result}</pre>
    </main>
  );
}

export default App;