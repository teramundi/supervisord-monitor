from xmlrpc.server import SimpleXMLRPCServer, SimpleXMLRPCRequestHandler
from socketserver import ThreadingMixIn
from xmlrpc.client import Fault
import time
import os

class Supervisor:
    def __init__(self):
        self.running = True
        self.pid = 100
        self.log = '\x1b[32mfixture log\x1b[0m\n' + ('info: Application ready; <sample> is plain text.\n' * 18)
    def getSupervisorVersion(self): return '4.2.5'
    def getAllProcessInfo(self):
        return [dict(name='worker', group='worker', statename='RUNNING' if self.running else 'STOPPED',
                     description='pid %s, uptime 0:01:00' % self.pid, pid=self.pid if self.running else 0,
                     stderr_logfile='/tmp/fixture.err', start=int(time.time()))]
    def readProcessStderrLog(self, name, offset, length): return self.log
    def startProcess(self, name, wait=True):
        if name == 'failing': raise Fault(10, 'SPAWN_ERROR: fixture failure')
        self.running=True; self.pid+=1; return True
    def stopProcess(self, name, wait=True):
        if name == 'failing-stop': raise Fault(60, 'NOT_RUNNING: fixture failure')
        self.running=False; return True
    def startAllProcesses(self, wait=True): self.startProcess('worker'); return []
    def stopAllProcesses(self, wait=True): self.stopProcess('worker'); return []
    def clearProcessLogs(self, name):
        if name == 'failing-clear': raise Fault(91, 'FAILED: failing-clear')
        self.log=''; return True

class Handler(SimpleXMLRPCRequestHandler):
    def do_POST(self):
        if self.path in ('/unauthorized', '/forbidden'):
            self.send_response(401 if self.path == '/unauthorized' else 403)
            self.end_headers()
        elif self.path == '/malformed':
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b'<broken')
        elif self.path == '/timeout':
            time.sleep(2)
        elif self.path == '/empty':
            self.close_connection = True
        else:
            super().do_POST()

class Server(ThreadingMixIn, SimpleXMLRPCServer):
    daemon_threads = True

time.sleep(float(os.environ.get('RPC_STARTUP_DELAY', '0')))
server=Server(('0.0.0.0',9001),requestHandler=Handler,allow_none=True,logRequests=False)
obj=Supervisor()
for name in ('getSupervisorVersion','getAllProcessInfo','readProcessStderrLog','startProcess','stopProcess','startAllProcesses','stopAllProcesses','clearProcessLogs'):
    server.register_function(getattr(obj,name),'supervisor.'+name)
server.serve_forever()
