import { Component } from "react";
import type { ErrorInfo, ReactNode } from "react";

type Props = {
  children: ReactNode;
};

type State = {
  hasError: boolean;
  message: string;
};

export class AppErrorBoundary extends Component<Props, State> {
  state: State = {
    hasError: false,
    message: "",
  };

  static getDerivedStateFromError(error: Error): State {
    return {
      hasError: true,
      message: error.message,
    };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error("App error:", error, info);
  }

  render() {
    if (this.state.hasError) {
      return (
        <main className="screen-center">
          <section className="login-card">
            <p className="eyebrow">Application Error</p>
            <h1>Something went wrong</h1>
            <p>
              Please refresh the page. If this continues, check the browser
              console and frontend terminal.
            </p>

            {this.state.message && (
              <pre className="error-debug">{this.state.message}</pre>
            )}

            <button
              className="primary-button"
              onClick={() => window.location.reload()}
            >
              Reload App
            </button>
          </section>
        </main>
      );
    }

    return this.props.children;
  }
}
